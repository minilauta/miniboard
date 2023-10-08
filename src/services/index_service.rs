use sqlx::MySqlPool;

use crate::types;

pub fn get_stats(db: &MySqlPool) -> types::Stats {
    let stats = sqlx::query_as("
        SELECT
            MAX(sq1.total_posts) AS total_posts,
            MAX(sq1.current_posts) AS current_posts,
            MAX(sq1.unique_posters) AS unique_posters,
            MAX(sq1.imported_posts) AS imported_posts,
            MAX(sq1.current_files) AS current_files,
            MAX(sq1.active_content) AS active_content
        FROM (
            SELECT
            SUM(sq11.total_posts) AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM (
            SELECT
                board_id AS board_id,
                MAX(post_id) AS total_posts
            FROM posts
            GROUP BY board_id
            ) AS sq11
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            COUNT(*) AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            COUNT(DISTINCT(ip)) AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            COUNT(*) AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            WHERE imported = 1

            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            COUNT(DISTINCT(file_hex)) AS current_files,
            NULL AS active_content
            FROM posts

            UNION ALL
            
            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,        SELECT
            MAX(sq1.total_posts) AS total_posts,
            MAX(sq1.current_posts) AS current_posts,
            MAX(sq1.unique_posters) AS unique_posters,
            MAX(sq1.imported_posts) AS imported_posts,
            MAX(sq1.current_files) AS current_files,
            MAX(sq1.active_content) AS active_content
        FROM (
            SELECT
            SUM(sq11.total_posts) AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM (
            SELECT
                board_id AS board_id,
                MAX(post_id) AS total_posts
            FROM posts
            GROUP BY board_id
            ) AS sq11
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            COUNT(*) AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            COUNT(DISTINCT(ip)) AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            
            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            COUNT(*) AS imported_posts,
            NULL AS current_files,
            NULL AS active_content
            FROM posts
            WHERE imported = 1

            UNION ALL

            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            COUNT(DISTINCT(file_hex)) AS current_files,
            NULL AS active_content
            FROM posts

            UNION ALL
            
            SELECT
            NULL AS total_posts,
            NULL AS current_posts,
            NULL AS unique_posters,
            NULL AS imported_posts,
            NULL AS current_files,
            SUM(sq12.file_size) AS active_content
            FROM (
            SELECT
                file_hex,
                file_size
            FROM posts
            GROUP BY file_hex
            ) AS sq12
        ) AS sq1
            FROM posts
            GROUP BY file_hex
            ) AS sq12
        ) AS sq1
    ")
    .fetch_one(db);

    return stats;
}
