ALTER TABLE `ai_executions`
  MODIFY COLUMN `step`
  ENUM('planning', 'research', 'writing', 'seo', 'compliance', 'image', 'review', 'backlink_suggestions', 'external_link_suggestions', 'internal_link_suggestions') NOT NULL;
