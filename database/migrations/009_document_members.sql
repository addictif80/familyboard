-- Many-to-many: a document can belong to multiple family members
CREATE TABLE IF NOT EXISTS document_members (
    document_id INT NOT NULL,
    user_id     INT NOT NULL,
    PRIMARY KEY (document_id, user_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing single-owner documents into the junction table
INSERT IGNORE INTO document_members (document_id, user_id)
SELECT id, user_id FROM documents WHERE user_id IS NOT NULL;
