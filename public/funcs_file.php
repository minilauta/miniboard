<?php

use Psr\Http\Message\UploadedFileInterface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/exception.php';

function funcs_file_validate_upload(UploadedFileInterface $input, bool $no_file_ok, array $mime_types, int $max_bytes): ?array {
  // check errors
  $error = $input->getError();
  if ($error === UPLOAD_ERR_NO_FILE && $no_file_ok) {
    return null;
  } else if ($error !== UPLOAD_ERR_OK) {
    throw new FuncException('funcs_file', 'validate_upload', "file upload error: {$error}", SC_BAD_REQUEST);
  }

  // get temp file handle
  $tmp_file = $input->getStream()->getMetadata('uri');

  // validate MIME type
  $finfo = finfo_open(FILEINFO_MIME);
  $file_mime = explode(';', finfo_file($finfo, $tmp_file))[0];
  finfo_close($finfo);

  // NOTE: mp3 files are tricky! check for id3v1 and id3v2 tags if finfo_file fails...
  $file_ext_pathinfo = pathinfo($input->getClientFilename(), PATHINFO_EXTENSION);
  if ($file_mime === 'application/octet-stream' && $file_ext_pathinfo === 'mp3') {
    $get_id3 = new getID3;
    $id3_info = $get_id3->analyze($tmp_file);
    if (isset($id3_info['id3v1']) || isset($id3_info['id3v2'])) {
      $file_mime = 'audio/mpeg';
    }
  }
  
  if (!isset($mime_types[$file_mime])) {
    throw new FuncException('funcs_file', 'validate_upload', "file mime type invalid: {$file_mime}", SC_BAD_REQUEST);
  }
  $file_ext = $mime_types[$file_mime];

  // validate file size
  $file_size = filesize($tmp_file);
  if ($file_size > $max_bytes) {
    throw new FuncException('funcs_file', 'validate_upload', "file size exceeds limit: {$file_size} bytes > {$max_bytes} bytes", SC_BAD_REQUEST);
  }

  // calculate md5 hash
  $file_md5 = md5_file($tmp_file);

  return [
    'tmp'            => $tmp_file,
    'mime'           => $file_mime,
    'ext'            => $file_ext[0],
    'size'           => $file_size,
    'md5'            => $file_md5
  ];
}

function funcs_file_execute_upload(UploadedFileInterface $file, ?array $file_info, array $file_collisions, int $max_w = 250, int $max_h = 250): array {
  // return if no file was uploaded
  if ($file_info == null) {
    return [
      'file'                => '',
      'file_hex'            => '',
      'file_original'       => '',
      'file_size'           => 0,
      'file_size_formatted' => '',
      'image_width'         => 0,
      'image_height'        => 0,
      'thumb'               => '',
      'thumb_width'         => 0,
      'thumb_height'        => 0
    ];
  }

  $file_name_client = $file->getClientFilename();

  // either use the uploaded file or an already existing file
  if (empty($file_collisions)) {
    $file_name = time() . substr(microtime(), 2, 3) . '.' . $file_info['ext'];
    $file_path = __DIR__ . '/src/' . $file_name;
    $file->moveTo($file_path);
    $file_hex = $file_info['md5'];
    $file_size = $file_info['size'];
    $file_size_formatted = funcs_common_human_filesize($file_size);
    $thumb_file_name = 'thumb_' . $file_name . '.png';
    $thumb_file_path = __DIR__ . '/src/' . $thumb_file_name;
    
    // strip metadata from all files
    $exiftool_status = funcs_file_strip_metadata($file_path);

    switch ($file_info['mime']) {
      case 'image/jpeg':
      case 'image/pjpeg':
      case 'image/png':
      case 'image/gif':
      case 'image/bmp':
      case 'image/webp':
        // make exiftool success mandatory for images
        if ($exiftool_status !== 0) {
          unlink($file_path);
          throw new FuncException('funcs_file', 'funcs_file_execute_upload', "exiftool returned an error status: {$exiftool_status}", SC_INTERNAL_ERROR);
        }

        $generated_thumb = funcs_file_generate_thumbnail($file_path, 'image/png', $thumb_file_path, $max_w, $max_h);
        $image_width = $generated_thumb['image_width'];
        $image_height = $generated_thumb['image_height'];
        $thumb_width = $generated_thumb['thumb_width'];
        $thumb_height = $generated_thumb['thumb_height'];
        break;
      case 'video/mp4':
      case 'video/webm':
        // make exiftool success mandatory for videos
        if ($exiftool_status !== 0) {
          unlink($file_path);
          throw new FuncException('funcs_file', 'funcs_file_execute_upload', "exiftool returned an error status: {$exiftool_status}", SC_INTERNAL_ERROR);
        }

        $ffprobe = FFMpeg\FFProbe::create();
        $video_duration = $ffprobe
          ->format($file_path)
          ->get('duration');

        $ffmpeg = FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($file_path);
        $video
          ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds($video_duration / 4))
          ->save($thumb_file_path);

        $generated_thumb = funcs_file_generate_thumbnail($thumb_file_path, 'image/png', $thumb_file_path, $max_w, $max_h);
        $image_width = $generated_thumb['image_width'];
        $image_height = $generated_thumb['image_height'];
        $thumb_width = $generated_thumb['thumb_width'];
        $thumb_height = $generated_thumb['thumb_height'];
        break;
      case 'audio/mpeg':
        $album_file_path = __DIR__ . '/src/' . 'album_' . $file_name;
        $album_file_path = funcs_file_get_mp3_album_art($file_path, $album_file_path);
        
        if ($album_file_path != null) {
          $generated_thumb = funcs_file_generate_thumbnail($album_file_path, 'image/png', $thumb_file_path, $max_w, $max_h);
          $image_width = $generated_thumb['image_width'];
          $image_height = $generated_thumb['image_height'];
          $thumb_width = $generated_thumb['thumb_width'];
          $thumb_height = $generated_thumb['thumb_height'];
        } else {
          $thumb_file_name = '';
          $image_width = 0;
          $image_height = 0;
          $thumb_width = 0;
          $thumb_height = 0;
        }
        break;
      default:
        unlink($file_path);
        throw new FuncException('funcs_file', 'funcs_file_execute_upload', "file ext type unsupported: {$file_info['mime']}", SC_INTERNAL_ERROR);
    }
  } else {
    $file_name = $file_collisions[0]['file'];
    $file_hex = $file_collisions[0]['file_hex'];
    $file_size = $file_collisions[0]['file_size'];
    $file_size_formatted = $file_collisions[0]['file_size_formatted'];
    $image_width = $file_collisions[0]['image_width'];
    $image_height = $file_collisions[0]['image_height'];
    $thumb_file_name = $file_collisions[0]['thumb'];
    $thumb_width = $file_collisions[0]['thumb_width'];
    $thumb_height = $file_collisions[0]['thumb_height'];
  }

  return [
    'file'                => $file_name,
    'file_hex'            => $file_hex,
    'file_original'       => $file_name_client,
    'file_size'           => $file_size,
    'file_size_formatted' => $file_size_formatted,
    'image_width'         => $image_width,
    'image_height'        => $image_height,
    'thumb'               => $thumb_file_name,
    'thumb_width'         => $thumb_width,
    'thumb_height'        => $thumb_height
  ];
}

function funcs_file_strip_metadata(string $file_path): int {
  // check if exiftool is available
  $exiftool_output = '';
  $exiftool_status = 1;
  exec('exiftool -ver', $exiftool_output, $exiftool_status);
  if ($exiftool_status !== 0) {
    return $exiftool_status;
  }

  // execute exiftool to strip any metadata
  $exiftool_output = '';
  $exiftool_status = 1;
  exec('exiftool -All= -overwrite_original_in_place ' . escapeshellarg($file_path), $exiftool_output, $exiftool_status);
  if ($exiftool_status !== 0) {
    return $exiftool_status;
  }

  return $exiftool_status;
}

function funcs_file_generate_thumbnail(string $file_path, string $file_mime, string $thumb_path, int $thumb_width, int $thumb_height): array {
  $image = new \claviska\SimpleImage();
  $image->fromFile($file_path);
  $image_width = $image->getWidth();
  $image_height = $image->getHeight();

  // re-calculate thumb dims
  $width_ratio = $thumb_width / $image_width;
  $height_ratio = $thumb_height / $image_height;
  $scale_factor = min($width_ratio, $height_ratio);
  $thumb_width = floor($image_width * $scale_factor);
  $thumb_height = floor($image_height * $scale_factor);

  $image
    ->thumbnail($thumb_width, $thumb_height, 'center')
    ->toFile($thumb_path, $file_mime, 100);
  
  return [
    'image_width'   => $image_width,
    'image_height'  => $image_height,
    'thumb_width'   => $thumb_width,
    'thumb_height'  => $thumb_height
  ];
}

function funcs_file_get_mp3_album_art(string $file_path, string $output_path): ?string {
  // get file info
  $get_id3 = new getID3;
  $id3_info = $get_id3->analyze($file_path);

  // extract album art data
  $album_mime = null;
  $album_path = null;
  if (isset($id3_info['comments']['picture'][0])) {
    $album_mime = $id3_info['comments']['picture'][0]['image_mime'];
    $album_ext = null;
    switch ($album_mime) {
      case 'image/jpeg':
      case 'image/pjpeg':
        $album_ext = 'jpg';
        break;
      case 'image/png':
        $album_ext = 'png';
        break;
      default:
        break;
    }

    if ($album_ext != null) {
      $album_data = $id3_info['comments']['picture'][0]['data'];
      $album_path = "{$output_path}.{$album_ext}";
      if (!file_put_contents($album_path, $album_data)) {
        return null;
      }
    }
  }

  return $album_path;
}
