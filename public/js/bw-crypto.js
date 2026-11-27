// ============================================
// Primitives cryptographiques du protocole client Bitwarden (utilisé par Vaultwarden) —
// réimplémentées ici pour que /settings puisse déverrouiller et gérer le coffre d'un membre
// directement dans FamilyBoard, sans jamais faire transiter le mot de passe maître ni le
// contenu déchiffré par le serveur FamilyBoard (voir VaultwardenProxyController : un simple
// relais d'octets opaques). Validé par rapport à une vraie instance Vaultwarden (inscription,
// connexion, création/lecture/modification/suppression d'un identifiant, déchiffrement final
// identique au texte d'origine) avant intégration ici.
//
// Ne prend en charge que le KDF PBKDF2-SHA256 (kdf=0) — Argon2id (le KDF par défaut des comptes
// récents) nécessiterait une bibliothèque WASM supplémentaire, hors périmètre pour l'instant ;
// voir bwIsKdfSupported().
const BwCrypto = (() => {
    const subtle = window.crypto.subtle;

    function b64ToBytes(b64) {
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }
    function bytesToB64(bytes) {
        let bin = '';
        for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin);
    }
    function utf8(s) { return new TextEncoder().encode(s); }

    async function pbkdf2(secretBytes, saltBytes, iterations, lengthBytes) {
        const key = await subtle.importKey('raw', secretBytes, 'PBKDF2', false, ['deriveBits']);
        const bits = await subtle.deriveBits(
            { name: 'PBKDF2', hash: 'SHA-256', salt: saltBytes, iterations },
            key, lengthBytes * 8
        );
        return new Uint8Array(bits);
    }

    async function hmacSha256(keyBytes, dataBytes) {
        const key = await subtle.importKey('raw', keyBytes, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
        const sig = await subtle.sign('HMAC', key, dataBytes);
        return new Uint8Array(sig);
    }

    // HKDF-Expand avec une sortie SHA-256 de 32 octets ne nécessite qu'un seul tour :
    // T(1) = HMAC(PRK, info || 0x01)
    async function hkdfExpand32(prk, infoStr) {
        const info = new Uint8Array([...utf8(infoStr), 0x01]);
        return hmacSha256(prk, info);
    }

    async function stretchKey(masterKey) {
        const encKey = await hkdfExpand32(masterKey, 'enc');
        const macKey = await hkdfExpand32(masterKey, 'mac');
        return { encKey, macKey };
    }

    async function deriveMasterKey(password, email, iterations) {
        return pbkdf2(utf8(password), utf8(email.trim().toLowerCase()), iterations, 32);
    }

    async function deriveServerHash(masterKey, password) {
        const bytes = await pbkdf2(masterKey, utf8(password), 1, 32);
        return bytesToB64(bytes);
    }

    async function aesCbcDecrypt(key, iv, data) {
        const cryptoKey = await subtle.importKey('raw', key, { name: 'AES-CBC' }, false, ['decrypt']);
        const plain = await subtle.decrypt({ name: 'AES-CBC', iv }, cryptoKey, data);
        return new Uint8Array(plain);
    }
    async function aesCbcEncrypt(key, iv, data) {
        const cryptoKey = await subtle.importKey('raw', key, { name: 'AES-CBC' }, false, ['encrypt']);
        const enc = await subtle.encrypt({ name: 'AES-CBC', iv }, cryptoKey, data);
        return new Uint8Array(enc);
    }

    function parseEncString(str) {
        const dotIdx = str.indexOf('.');
        const type = parseInt(str.slice(0, dotIdx), 10);
        const parts = str.slice(dotIdx + 1).split('|');
        return { type, iv: b64ToBytes(parts[0]), data: b64ToBytes(parts[1]), mac: parts[2] ? b64ToBytes(parts[2]) : null };
    }
    function buildEncString(type, iv, data, mac) {
        let s = `${type}.${bytesToB64(iv)}|${bytesToB64(data)}`;
        if (mac) s += `|${bytesToB64(mac)}`;
        return s;
    }

    function concatBytes(...arrs) {
        const total = arrs.reduce((n, a) => n + a.length, 0);
        const out = new Uint8Array(total);
        let off = 0;
        for (const a of arrs) { out.set(a, off); off += a.length; }
        return out;
    }
    function constEq(a, b) {
        if (a.length !== b.length) return false;
        let diff = 0;
        for (let i = 0; i < a.length; i++) diff |= a[i] ^ b[i];
        return diff === 0;
    }

    async function decryptEncStringRaw(str, encKey, macKey) {
        const { type, iv, data, mac } = parseEncString(str);
        if (type !== 2) throw new Error('Type de chiffrement non pris en charge (' + type + ')');
        if (macKey) {
            const expectedMac = await hmacSha256(macKey, concatBytes(iv, data));
            if (!constEq(expectedMac, mac)) throw new Error('Intégrité invalide (MAC).');
        }
        return aesCbcDecrypt(encKey, iv, data);
    }
    async function decryptEncStringUtf8(str, encKey, macKey) {
        if (!str) return '';
        const raw = await decryptEncStringRaw(str, encKey, macKey);
        return new TextDecoder().decode(raw);
    }
    async function encryptEncStringUtf8(plain, encKey, macKey) {
        const iv = window.crypto.getRandomValues(new Uint8Array(16));
        const data = await aesCbcEncrypt(encKey, iv, utf8(plain ?? ''));
        const mac = await hmacSha256(macKey, concatBytes(iv, data));
        return buildEncString(2, iv, data, mac);
    }

    /** Déchiffre la "Key" du profil (EncString) avec la clé maître étirée -> 64 octets :
     *  32 premiers = clé de chiffrement du coffre, 32 derniers = clé HMAC du coffre. */
    async function decryptUserKey(encStringKey, stretchEnc, stretchMac) {
        const raw = await decryptEncStringRaw(encStringKey, stretchEnc, stretchMac);
        return { encKey: raw.slice(0, 32), macKey: raw.slice(32, 64) };
    }

    function isKdfSupported(kdf) {
        return kdf === 0; // PBKDF2-SHA256 uniquement pour l'instant (voir en-tête du fichier)
    }

    return {
        deriveMasterKey, stretchKey, deriveServerHash, decryptUserKey,
        decryptEncStringUtf8, encryptEncStringUtf8, isKdfSupported,
    };
})();
