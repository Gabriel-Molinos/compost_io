ALTER TABLE `ai_executions`
  MODIFY COLUMN `step`
  ENUM('planning', 'research', 'writing', 'seo', 'compliance', 'image', 'review', 'backlink_suggestions') NOT NULL;
