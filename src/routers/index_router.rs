use crate::{services::index_service, DbPool};
use actix_web::{get, http::header::ContentType, web, HttpResponse, Responder};

#[get("/")]
async fn index(pool: web::Data<DbPool>) -> impl Responder {
    let mut conn = pool.get().expect("error getting connection from r2d2 pool");

    let stats = index_service::get_stats(&mut conn);

    HttpResponse::Ok()
        .content_type(ContentType::json())
        .json(stats)
}
