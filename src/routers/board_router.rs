use actix_web::{get, web, HttpResponse, Responder, HttpRequest};

use crate::{services::index_service, AppData};

#[get("/{board_id}")]
async fn board(app: web::Data<AppData>, req: HttpRequest, path: web::Path<String>) -> impl Responder {
    // get global conf
    let conf = &app.conf;

    // get data from db
    let mut db = app.dbpl
        .get()
        .unwrap();
    let stats = index_service::get_stats(&mut db);

    // render html template
    let mut ctx = tera::Context::new();
    ctx.insert("stats", &stats);
    ctx.insert("site_name", &conf.site.name);
    ctx.insert("site_desc", &conf.site.desc);
    ctx.insert("disclaimer", &conf.site.disc);
    ctx.insert("contact", &conf.site.contact);
    ctx.insert("title", &conf.site.name);
    ctx.insert("subtitle", "");
    ctx.insert("logo", &conf.site.logo);
    ctx.insert("styles", &conf.site.styles);
    let selected_style = req.cookie("miniboard/style")
        .map_or(conf.site.default_style.clone(), |x| String::from(x.value()));
    ctx.insert("selected_style", &selected_style);
    ctx.insert("rules", &conf.site.rules);
    let boards = vec!["Main", "Random", "Anime"];
    ctx.insert("boards", &boards);

    let html = app.tmpl.render("index.html", &ctx)
        .unwrap();

    HttpResponse::Ok()
        .body(html)
}
