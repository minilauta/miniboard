use actix_web::{web, App, HttpServer};
use r2d2_mysql::mysql::OptsBuilder;
use r2d2_mysql::r2d2;
use routers::index_router;
use std::env;

extern crate r2d2_mysql;

mod routers;
mod services;
mod types;

type DbPool = r2d2::Pool<r2d2_mysql::MySqlConnectionManager>;

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
            .service(index_router::index)
    })
    .bind(("127.0.0.1", 9090))?
    .run()
    .await
}
