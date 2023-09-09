use std::borrow::BorrowMut;

use crate::{services::index_service, AppData};
use actix_web::{get, http::header::ContentType, web, HttpResponse, Responder, HttpRequest, cookie::Cookie};
use tera::Context;

#[get("/")]
async fn index(app: web::Data<AppData>, req: HttpRequest) -> impl Responder {
    // get data from db
    let mut db = app.dbpl
        .get()
        .unwrap();
    let stats = index_service::get_stats(&mut db);

    // render html template
    let mut ctx = tera::Context::new();
    ctx.insert("stats", &stats);
    ctx.insert("site_name", "Miniboard");
    ctx.insert("site_desc", "v2");
    ctx.insert("disclaimer", "All trademarks, etc...");
    ctx.insert("contact", "info@localhost");
    ctx.insert("title", "Miniboard");
    ctx.insert("subtitle", "v2");
    ctx.insert("logo", "logo.png");
    let styles = vec!["futaba", "yotsuba", "yotsuba_blue", "zenburn"];
    ctx.insert("styles", &styles);
    let selected_style = req.cookie("miniboard/style")
        .map_or(String::from("futaba"), |x| String::from(x.value()));
    ctx.insert("selected_style", &selected_style);
    ctx.insert("rules", "
        <strong>Forbidden on all boards</strong>
        <ol>
            <li>Do not break rules</li>
            <li>Read rule #1</li>
        </ol>
    ");
    let boards = vec!["Main", "Random", "Anime"];
    ctx.insert("boards", &boards);

    let html = app.tmpl.render("index.html", &ctx)
        .unwrap();

    HttpResponse::Ok()
        .body(html)
}
