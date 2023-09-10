use std::fs::File;
use std::env;

use actix_web::web::Data;
use actix_web::{App, HttpServer};
use r2d2_mysql::mysql::OptsBuilder;
use r2d2_mysql::r2d2;
use routers::index_router;
use tera::Tera;

use types::GlobalConf;

extern crate r2d2_mysql;

mod routers;
mod services;
mod types;

type DbPool = r2d2::Pool<r2d2_mysql::MySqlConnectionManager>;

struct AppData {
    conf: GlobalConf,
    tmpl: Tera,
    dbpl: DbPool,
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    env_logger::init();

    let conf_reader = File::open("./config/global.yml")
        .expect("failed to read global conf from file");
    let global_conf: GlobalConf = serde_yaml::from_reader(conf_reader)
        .expect("failed to load global conf from file");

    let tmpl = Tera::new("templates/**/*")
        .unwrap();

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
            .app_data(Data::new(AppData {
                conf: global_conf.clone(),
                tmpl: tmpl.clone(),
                dbpl: db_pool.clone(),
            }))
            .service(index_router::index)
            .service(
                actix_files::Files::new("/", "./public/")
                    .show_files_listing()
                    .use_last_modified(true)
            )
    })
    .bind(("127.0.0.1", 9090))?
    .run()
    .await
}
