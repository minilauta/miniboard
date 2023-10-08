use crate::types::Post;
use crate::{types, DbConn};
use r2d2_mysql::mysql::params;
use r2d2_mysql::mysql::prelude::Queryable;
use r2d2_mysql::{r2d2, MySqlConnectionManager};

pub fn get_posts(
    db: &mut DbConn,
    board_id: String,
    parent_id: u32,
    offset: u32,
    limit: u32
) -> Vec<Post> {
    let stmt = db
        .prep("
            SELECT
                id,
                board_id,
                parent_id,
                post_id,
                timestamp,
                bumped,
                ip,
                role,
                name,
                tripcode,
                email,
                subject,
                nameblock,
                password,
                message,
                message_rendered,
                message_truncated,
                file,
                file_rendered,
                file_hex,
                file_original,
                file_size,
                file_size_formatted,
                image_width,
                image_height,
                thumb,
                thumb_width,
                thumb_height,
                embed,
                country,
                stickied,
                locked,
                moderated,
                imported
            FROM posts
            WHERE board_id = :board_id AND parent_id = :parent_id
            ORDER BY stickied DESC, bumped DESC
            LIMIT :limit OFFSET :offset
        ")
        .unwrap();
    let rows = db
        .exec_first(&stmt, params! {
            "board_id" => board_id,
            "parent_id" => parent_id,
            "offset" => offset,
            "limit" => limit
        })
        .unwrap();
    

    return posts;
}
