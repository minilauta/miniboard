<?php
require_once __DIR__ . '/db_handle.php';

function insert_refresh_board(array $board): bool {
  // convert bools to int
  $board['nsfw'] = $board['nsfw'] == true ? 1 : 0;
  $board['hidden'] = $board['hidden'] == true ? 1 : 0;
  $board['flags'] = $board['flags'] == true ? 1 : 0;
  $board['alwaysnoko'] = $board['alwaysnoko'] == true ? 1 : 0;
  $board['nofileok'] = $board['nofileok'] == true ? 1 : 0;
  $board['text'] = $board['text'] == true ? 1 : 0;

  // convert arrays to json
  $board['fields_post'] = json_encode($board['fields_post']);
  $board['mime_ext_types'] = json_encode($board['mime_ext_types']);
  $board['embed_types'] = json_encode($board['embed_types']);

  $dbh = get_db_handle();
  $sth = $dbh->prepare('
    INSERT INTO boards (
      id,
      name,
      description,
      type,
      anonymous,
      hashid_salt,
      nsfw,
      hidden,
      flags,
      role,
      alwaysnoko,
      threads_per_page,
      threads_per_catalog_page,
      posts_per_preview,
      truncate,
      max_threads,
      max_replies,
      maxkb,
      nofileok,
      text,
      max_width,
      max_height,
      post_fields,
      mime_types,
      embed_types
    )
    VALUES (
      :id,
      :name,
      :desc,
      :type,
      :anonymous,
      :hashid_salt,
      :nsfw,
      :hidden,
      :flags,
      :req_role,
      :alwaysnoko,
      :threads_per_page,
      :threads_per_catalog_page,
      :posts_per_preview,
      :truncate,
      :max_threads,
      :max_replies,
      :maxkb,
      :nofileok,
      :text,
      :max_width,
      :max_height,
      :fields_post,
      :mime_ext_types,
      :embed_types
    )
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      description = VALUES(description),
      type = VALUES(type),
      anonymous = VALUES(anonymous),
      hashid_salt = VALUES(hashid_salt),
      nsfw = VALUES(nsfw),
      hidden = VALUES(hidden),
      flags = VALUES(flags),
      role = VALUES(role),
      alwaysnoko = VALUES(alwaysnoko),
      threads_per_page = VALUES(threads_per_page),
      threads_per_catalog_page = VALUES(threads_per_catalog_page),
      posts_per_preview = VALUES(posts_per_preview),
      truncate = VALUES(truncate),
      max_threads = VALUES(max_threads),
      max_replies = VALUES(max_replies),
      maxkb = VALUES(maxkb),
      nofileok = VALUES(nofileok),
      text = VALUES(text),
      max_width = VALUES(max_width),
      max_height = VALUES(max_height),
      post_fields = VALUES(post_fields),
      mime_types = VALUES(mime_types),
      embed_types = VALUES(embed_types)
  ');
  return $sth->execute($board);
}
