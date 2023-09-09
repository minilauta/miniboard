use crate::types;
use r2d2_mysql::mysql::prelude::Queryable;
use r2d2_mysql::{r2d2, MySqlConnectionManager};

pub fn get_stats(db: &mut r2d2::PooledConnection<MySqlConnectionManager>) -> types::Stats {
    let stats = db
        .query_map(
            "
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
        ",
            |(
                total_posts,
                current_posts,
                unique_posters,
                imported_posts,
                current_files,
                active_content,
            )| {
                types::Stats {
                    total_posts,
                    current_posts,
                    unique_posters,
                    imported_posts,
                    current_files,
                    active_content,
                }
            },
        )
        .expect("error executing query")
        .remove(0);

    return stats;
}
