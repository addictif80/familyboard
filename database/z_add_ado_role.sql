-- Rôle "ado" : accès complet aux modules du quotidien, mais automatiquement privé des modules
-- financiers/juridiques/administratifs sensibles (voir Family::ADO_RESTRICTED_MODULES et
-- BaseController::requireModule()). Un membre existant est basculé vers ce rôle par un
-- administrateur famille (voir SettingsController::setAdo()/unsetAdo()) — ce n'est pas un type
-- de compte créé par invitation séparée, contrairement au co-parent.
ALTER TABLE users MODIFY COLUMN role ENUM('admin','member','coparent','ado') NOT NULL DEFAULT 'member';
