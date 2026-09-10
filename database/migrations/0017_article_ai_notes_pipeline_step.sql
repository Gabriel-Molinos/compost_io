ALTER TABLE `article_ai_notes`
  MODIFY COLUMN `step` ENUM('planning', 'research', 'writing', 'seo', 'compliance', 'image', 'review', 'pipeline') NOT NULL;
