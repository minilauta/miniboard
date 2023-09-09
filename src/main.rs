use std::env;

use actix_web::{get, post, web, App, HttpResponse, HttpServer, Responder, http::header::ContentType};
use r2d2_mysql::mysql::prelude::Queryable;
use r2d2_mysql::r2d2;
use r2d2_mysql::mysql::OptsBuilder;

extern crate r2d2_mysql;

type DbPool = r2d2::Pool<r2d2_mysql::MySqlConnectionManager>;

mod types;

#[get("/")]
async fn index(
    pool: web::Data<DbPool>
) -> impl Responder {
    let mut conn = pool
        .get()
        .expect("error getting connection from r2d2 pool");

    let stats = conn
        .query_map("
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
        |(total_posts, current_posts, unique_posters, imported_posts, current_files, active_content)|{
            types::Stats { total_posts, current_posts, unique_posters, imported_posts, current_files, active_content }
        },)
        .expect("error executing query");
    let stats = stats
        .first()
        .expect("no stats returned");

    HttpResponse::Ok()
        .content_type(ContentType::json())
        .json(stats)
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    let db_opts = OptsBuilder::new()
        .ip_or_hostname(env::var("DB_HOST").ok())
        .db_name(env::var("DB_NAME").ok())
        .user(env::var("DB_USER").ok())
        .pass(env::var("DB_PASS").ok());
    let db_manager = r2d2_mysql::MySqlConnectionManager::new(db_opts);
    let db_pool = r2d2::Pool::builder()
        .build(db_manager)
        .expect("failed to build r2d2 connection pool");

    HttpServer::new(move || {
        App::new()
            .app_data(web::Data::new(db_pool.clone()))
            .service(index)
    })
    .bind(("127.0.0.1", 9090))?
    .run()
    .await
}
