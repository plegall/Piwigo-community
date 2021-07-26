<?php
define('PHPWG_ROOT_PATH', '../../');
include(PHPWG_ROOT_PATH.'include/common.inc.php');

check_status(ACCESS_GUEST);

global $conf;

try {
  $download_file_id = $_GET['id'];

  $download_file_name = preg_replace('/[[:space:]]+/', '-', $conf['gallery_title']) . '-download-' . $download_file_id;
  $zip_file = COMMUNITY_DOWNLOAD_LOCAL .$download_file_name. '.zip';

  if (file_exists($zip_file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition:attachment; filename="'.basename($zip_file).'"');
    header('Content-Length: ' . filesize($zip_file));

    flush();
    readfile($zip_file);
    unlink($zip_file);
  } else {
    $redirect_url = make_index_url(array('section' => 'edit_photos'));
    header('Location: '.$redirect_url);
    exit("File does not exist");
  }
}
catch (Exception $e) {
  echo $e->getMessage();
}

exit(0);
