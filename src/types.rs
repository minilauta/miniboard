use serde::{Serialize, Deserialize};

#[derive(Serialize, Deserialize)]
pub struct Stats {
    pub total_posts: Option<u32>,
    pub current_posts: Option<u32>,
    pub unique_posters: Option<u32>,
    pub imported_posts: Option<u32>,
    pub current_files: Option<u32>,
    pub active_content: Option<u32>
}

#[derive(Serialize, Deserialize)]
pub struct Post {
    pub id: u32,
    pub board_id: String,
    pub parent_id: u32,
    pub post_id: u32,
    pub timestamp: u32,
    pub bumped: u32,
    pub ip: String,
    pub role: u8,
    pub name: String,
    pub tripcode: String,
    pub email: String,
    pub subject: String,
    pub nameblock: String,
    pub password: String,
    pub message: String,
    pub message_rendered: String,
    pub message_truncated: String,
    pub file: String,
    pub file_rendered: String,
    pub file_hex: String,
    pub file_original: String,
    pub file_size: u32,
    pub file_size_formatted: String,
    pub image_width: u32,
    pub image_height: u32,
    pub thumb: String,
    pub thumb_width: String,
    pub thumb_height: String,
    pub embed: u8,
    pub country: String,
    pub stickied: bool,
    pub locked: bool,
    pub moderated: bool,
    pub imported: bool,
}

#[derive(Serialize, Deserialize)]
pub struct PostFormData {
    pub name: String,
    pub email: String,
    pub subject: String,
    pub message: String,
    // file ? how
    pub spoiler: bool,
    pub anonfile: bool,
    pub embed: String,
    pub password: String,
    pub board: Option<String>,
    pub capcode: Option<u8>,
}
