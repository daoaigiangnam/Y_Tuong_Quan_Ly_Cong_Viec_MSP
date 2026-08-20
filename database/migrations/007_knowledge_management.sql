CREATE TABLE IF NOT EXISTS knowledge_articles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    body LONGTEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    customer_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    reviewer_user_id BIGINT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_knowledge_article_no (article_no),
    UNIQUE KEY uq_knowledge_slug (slug),
    KEY idx_knowledge_status (status),
    KEY idx_knowledge_category (category),
    KEY idx_knowledge_customer (customer_id),
    KEY idx_knowledge_service (service_id),
    KEY idx_knowledge_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    event VARCHAR(50) NOT NULL,
    value VARCHAR(255) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_knowledge_history_article (article_id),
    KEY idx_knowledge_history_created (created_at),
    CONSTRAINT fk_knowledge_history_article FOREIGN KEY (article_id) REFERENCES knowledge_articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_links (
    article_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    linked_by_user_id BIGINT UNSIGNED NOT NULL,
    linked_at DATETIME NOT NULL,
    PRIMARY KEY (article_id, entity_type, entity_id),
    KEY idx_knowledge_links_entity (entity_type, entity_id),
    CONSTRAINT fk_knowledge_links_article FOREIGN KEY (article_id) REFERENCES knowledge_articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
