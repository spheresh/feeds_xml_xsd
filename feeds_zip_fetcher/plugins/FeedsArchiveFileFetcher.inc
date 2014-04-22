<?php
/**
 * @file
 * Home of the FeedsArchiveFileFetcher and related classes.
 */
/**
 * Definition of the import batch object created on the fetching stage by
 * FeedsArchiveFileFetcher.
 */
class FeedsArchiveFileFetcherResult extends FeedsFileFetcherResult {
  /**
   * Overrides parent::getRaw().
   */
  public function getRaw() {
    // Not implemented;
    return;
  }
}
/**
 * Fetches data via HTTP.
 */
class FeedsArchiveFileFetcher extends FeedsFileFetcher {
  public $processed_archives;
  /**
   * Implements FeedsFetcher::fetch().
   *
   * If there are multiple archives to be imported from, this function will get
   * called multiple times.
   */
  public function fetch(FeedsSource $source) {
    $source_config = $source->getConfigFor($this);
    $state = $source->state(FEEDS_FETCH);
    // If this is a file, just return a FeedsFileFetcherResult for the
    // containing feed source.
    if (is_file($source_config['source'])) {
      try {
        $state->extract_dir = $this->extractFeedArchive($source_config['source']);
        $feed_source_file = $this->getFeedSourceFile($state->extract_dir);
        // Store processed archive and directory for postprocessing,
        // i.e. to delete archive and temp. directory.
        $state->processed_archives[$source_config['source']] = $state->extract_dir;
      }
      catch (FeedsNotExistingException $e) {
        // Do nothing.
      }
      return new FeedsFileFetcherResult($feed_source_file);
    }
    // Batch if this is a directory.
    $files = array();
    if (!isset($state->files)) {
      $state->files = $this->listFiles($source_config['source']);
      $state->total = count($state->files);
    }
    if (count($state->files)) {
      // Get next file.
      $archive_file = array_shift($state->files);
      // Set batch progress.
      $state->progress($state->total, $state->total - count($state->files));
      // Get the feed source from the archive.
      $state->extract_dir = $this->extractFeedArchive($archive_file);
      $state->current_archive_file = $archive_file;
      $feed_source_file = $this->getFeedSourceFile($state->extract_dir);
      // Store processed archive and directory for postprocessing,
      // i.e. to delete archive and temp. directory.
      $state->processed_archives[$archive_file] = $state->extract_dir;
      $fetcher_result = new FeedsFileFetcherResult($feed_source_file);
      module_invoke_all('feeds_after_fetch_archive_file', $source, $fetcher_result);
      return $fetcher_result;
    }
    throw new Exception(t('Resource is not a file or it is an empty directory: %source', array('%source' => $source_config['source'])));
  }
  /**
   * Returns an array of files in a directory.
   *
   * @param string $dir
   *   A stream wreapper URI that is a directory.
   *
   * @return array
   *   An array of stream wrapper URIs pointing to files. The array is empty if
   *   no files could be found. Never contains directories.
   */
  protected function listFiles($dir) {
    $dir = file_stream_wrapper_uri_normalize($dir);
    $files = array();
    if ($items = @scandir($dir)) {
      // Scan for supported archives only.
      $extensions = archiver_get_extensions();
      // Regex from file_validate_extensions().
      $regex = '/\.(' . preg_replace('/ +/', '|', preg_quote($extensions)) . ')$/i';
      foreach ($items as $item) {
        if (is_file("$dir/$item") && strpos($item, '.') !== 0 && preg_match($regex, $item)) {
          $files[] = "$dir/$item";
        }
      }
    }
    return $files;
  }
  /**
   * Extract the archive to a temporary directory.
   *
   * @param [type] $archive_file [description]
   *
   * @return [type] [description]
   */
  protected function extractFeedArchive($archive_file){
    $archiver = archiver_get_archiver($archive_file);
    if (!$archiver) {
      throw new Exception(t('Archive type of %archive_file is not supported.', array('%archive_file' => $archive_file)));
    }
    // Extract archive in temporary directory.
    $directory = 'temporary://feeds-importer-' . $this->id . '-' . date('Ymd-Hi-') . substr(drupal_hash_base64(drupal_random_bytes(8)), 0, 8);
    if (!file_exists($directory)) {
      mkdir($directory);
    }
    $archiver->extract($directory);
    return $directory;
  }
  /**
   * Gets the feed source file.
   *
   * @param string $extract_dir
   *  The directory where the archive was extracted to.
   *
   * @return string
   *  The feed source.
   */
  protected function getFeedSourceFile($extract_dir) {
    // TODO: replace placeholders i.e. %archive-name in "%archive-name.xml"
    $feed_source_file = $this->config['feed_source_file_name'];
    if (file_exists($extract_dir . '/' . $feed_source_file)) {
      return $extract_dir . '/' . $feed_source_file;
    }
  }
  /**
   * Source form.
   */
  public function sourceForm($source_config) {
    $form = array();
    $form['fid'] = array(
      '#type' => 'value',
      '#value' => empty($source_config['fid']) ? 0 : $source_config['fid'],
    );
    if (empty($this->config['archive_direct'])) {
      $form['source'] = array(
        '#type' => 'value',
        '#value' => empty($source_config['source']) ? '' : $source_config['source'],
      );
      $form['upload'] = array(
        '#type' => 'file',
        '#title' => empty($this->config['archive_direct']) ? t('File') : NULL,
        '#description' => empty($source_config['source']) ? t('Select an archive file from your local system.') : t('Select a different archive file from your local system.'),
        '#theme' => 'feeds_upload',
        '#file_info' => empty($source_config['fid']) ? NULL : file_load($source_config['fid']),
        '#size' => 10,
      );
    }
    else {
      $form['source'] = array(
        '#type' => 'textfield',
        '#title' => t('File or directory'),
        '#description' => t('Specify a path to a file or a directory. Prefix the path with a scheme. Available schemes: @schemes.', array('@schemes' => implode(', ', $this->config['archive_allowed_schemes']))),
        '#default_value' => empty($source_config['source']) ? $this->config['archive_directory'] : $source_config['source'],
      );
    }
    return $form;
  }
  /**
   * Overrides parent::sourceFormValidate().
   */
  public function sourceFormValidate(&$values) {
    $values['source'] = trim($values['source']);
    // Uploaded File
    if (empty($this->config['archive_direct'])) {
      $feed_dir = $this->config['archive_directory'];
      if (!file_prepare_directory($feed_dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
        if (user_access('administer feeds')) {
          $plugin_key = feeds_importer($this->id)->config[$this->pluginType()]['plugin_key'];
          $link = url('admin/structure/feeds/' . $this->id . '/settings/' . $plugin_key);
          form_set_error('feeds][FeedsArchiveFileFetcher][source', t('Upload failed. Please check the upload <a href="@link">settings.</a>', array('@link' => $link)));
        }
        else {
          form_set_error('feeds][FeedsArchiveFileFetcher][source', t('Upload failed. Please contact your site administrator.'));
        }
        watchdog('feeds', 'The upload directory %directory required by a feed could not be created or is not accessible. A newly uploaded file could not be saved in this directory as a consequence, and the upload was canceled.', array('%directory' => $feed_dir));
      }
      // Validate and save uploaded file.
      elseif ($file = file_save_upload('feeds', array('file_validate_extensions' => array(archiver_get_extensions())), $feed_dir)) {
        $values['source'] = $file->uri;
        $values['file'] = $file;
      }
      elseif (empty($values['source'])) {
        form_set_error('feeds][FeedsArchiveFileFetcher][source', t('Please upload a file.'));
      }
      else {
        // File present from previous upload. Nothing to validate.
      }
    }
    // Path to a file o directory of files
    else {
      // Check if chosen url scheme is allowed.
      $scheme = file_uri_scheme($values['source']);
      if (!$scheme || !in_array($scheme, $this->config['archive_allowed_schemes'])) {
        form_set_error('feeds][FeedsArchiveFileFetcher][source', t("The file needs to reside within the site's files directory, its path needs to start with scheme://. Available schemes: @schemes.", array('@schemes' => implode(', ', $this->config['archive_allowed_schemes']))));
      }
      // Check wether the given path exists.
      elseif (!file_exists($values['source'])) {
        form_set_error('feeds][FeedsArchiveFileFetcher][source', t('The specified file or directory does not exist.'));
      }
    }
  }
  /**
   * Overrides parent::configDefaults().
   */
  public function configDefaults() {
    $schemes = $this->getSchemes();
    $scheme = in_array('private', $schemes) ? 'private' : 'public';
    return array(
      'archive_direct' => FALSE,
      'archive_directory' => $scheme . '://feeds',
      'archive_allowed_schemes' => $schemes,
      'feed_source_allowed_extensions' => 'txt csv tsv xml opml',
      'feed_source_file_name' => 'feed.xml',
    );
  }
  /**
   * Overrides parent::configForm().
   */
  public function configForm(&$form_state) {
    $form = array();
    $form['archive'] = array(
      '#type' => 'fieldset',
      '#title' => t('Feed archive file'),
    );
    $form['archive']['supported_extensions'] = array(
      '#type' => 'item',
      '#title' => t('Supported archive types'),
      '#markup' => archiver_get_extensions(),
    );
    $form['archive']['archive_direct'] = array(
      '#type' => 'checkbox',
      '#title' => t('Supply path to file or directory directly'),
      '#description' => t('For experts. Lets users specify a path to a file <em>or a directory of files</em> directly,
        instead of a file upload through the browser. This is useful when the files that need to be imported
        are already on the server.'),
      '#default_value' => $this->config['archive_direct'],
    );
    $form['archive']['archive_directory'] = array(
      '#type' => 'textfield',
      '#title' => t('Upload directory'),
      '#description' => t('Directory where uploaded files get stored. Prefix the path with a scheme. Available schemes: @schemes.', array('@schemes' => implode(', ', $this->getSchemes()))),
      '#default_value' => $this->config['archive_directory'],
      '#states' => array(
        'visible' => array(':input[name="direct"]' => array('checked' => FALSE)),
        'required' => array(':input[name="direct"]' => array('checked' => FALSE)),
      ),
    );
    if ($options = $this->getSchemeOptions()) {
      $form['archive']['archive_allowed_schemes'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Allowed schemes'),
        '#default_value' => $this->config['archive_allowed_schemes'],
        '#options' => $options,
        '#description' => t('Select the schemes you want to allow for direct upload.'),
        '#states' => array(
          'visible' => array(':input[name="direct"]' => array('checked' => TRUE)),
        ),
      );
    }
    $form['feed_source'] = array(
      '#type' => 'fieldset',
      '#title' => t('Feed source file inside the archive'),
    );
    $form['feed_source']['feed_source_allowed_extensions'] = array(
      '#type' => 'textfield',
      '#title' => t('Allowed file extensions'),
      '#description' => t('Allowed file extensions for feed source file.'),
      '#default_value' => $this->config['feed_source_allowed_extensions'],
      '#required' => TRUE,
    );
    $form['feed_source']['feed_source_file_name'] = array(
      '#type' => 'textfield',
      '#title' => t('File name'),
      '#description' => t('Name of feed source file inside archive.'),
      '#default_value' => $this->config['feed_source_file_name'],
    );
    return $form;
  }
  /**
   * Overrides parent::configFormValidate().
   *
   * Ensure that the chosen directory is accessible.
   */
  public function configFormValidate(&$values) {
    $values['archive_directory'] = trim($values['archive_directory']);
    $values['archive_allowed_schemes'] = array_filter($values['archive_allowed_schemes']);
    if (!$values['archive_direct']) {
      // Ensure that the upload directory field is not empty when not in
      // direct-mode.
      if (!$values['archive_directory']) {
        form_set_error('archive_directory', t('Please specify an upload directory.'));
        // Do not continue validating the directory if none was specified.
        return;
      }
      // Validate the URI scheme of the upload directory.
      $scheme = file_uri_scheme($values['archive_directory']);
      if (!$scheme || !in_array($scheme, $this->getSchemes())) {
        form_set_error('archive_directory', t('Please enter a valid scheme into the directory location.'));
        // Return here so that attempts to create the directory below don't
        // throw warnings.
        return;
      }
      // Ensure that the upload directory exists.
      if (!file_prepare_directory($values['archive_directory'], FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
        form_set_error('archive_directory', t('The chosen directory does not exist and attempts to create it failed.'));
      }
    }
    // Ensure feed source file name has valid extension.
    if ($values['feed_source_file_name']) {
      $allowed_extensions = explode(' ', $values['feed_source_allowed_extensions']);
      $extension = pathinfo($values['feed_source_file_name'], PATHINFO_EXTENSION);
      if (!$extension || !in_array($extension, $allowed_extensions)) {
        form_set_error('feed_source_file_name', t('The file extension does not match any allowed extension.'));
      }
    }
  }
}
