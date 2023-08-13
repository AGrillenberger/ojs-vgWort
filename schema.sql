CREATE TABLE pixel_tags (
    pixel_tag_id INTEGER(8) PRIMARY KEY AUTO_INCREMENT,
    context_id INTEGER(8) NOT NULL,
    submission_id INTEGER(8),
    private_code VARCHAR(255) NOT NULL,
    public_code VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    date_ordered VARCHAR(255) NOT NULL,
    date_assigned TEXT,
    date_registered TEXT,
    date_removed TEXT,
    status INTEGER(2) NOT NULL,
    text_type INTEGER(2) NOT NULL,
    message TEXT
)