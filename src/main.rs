use std::env;
use std::fs::File;

use actix_web::web::Data;
use actix_web::{App, HttpServer};
use routers::index_router;
use sqlx::mysql::{MySqlConnectOptions, MySqlPoolOptions};
use sqlx::MySqlPool;
use tera::Tera;

use types::GlobalConf;

mod routers;
mod services;
mod types;

struct AppData {
    conf: GlobalConf,
    tmpl: Tera,
    dbpl: MySqlPool,
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    env_logger::init();

    let conf_reader = File::open("./config/global.yml")
        .expect("failed to read global conf from file");
    let global_conf: GlobalConf = serde_yaml::from_reader(conf_reader)
        .expect("failed to load global conf from file");

    let tmpl = Tera::new("templates/**/*").unwrap();

    let db_opts = MySqlConnectOptions::new()
        .host(env::var("DB_HOST").unwrap().as_str())
        .database(env::var("DB_NAME").unwrap().as_str())
        .username(env::var("DB_USER").unwrap().as_str())
        .password(env::var("DB_PASS").unwrap().as_str());

    let db_pool = match MySqlPoolOptions::new()
        .max_connections(10)
        .connect_with(db_opts)
        .await
    {
        Ok(db_pool) => db_pool,
        Err(err) => std::process::exit(1),
    };

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
                    .use_last_modified(true),
            )
    })
    .bind(("127.0.0.1", 9090))?
    .run()
    .await
}
