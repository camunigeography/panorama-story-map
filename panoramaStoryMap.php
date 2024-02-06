<?php

# Class to create a template application
class panoramaStoryMap extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName'		=> 'Panorama story map',
			'div'					=> strtolower (__CLASS__),
			'tabUlClass'			=> 'tabsflat',
			'databaseStrictWhere'	=> true,
			'nativeTypes'			=> true,
			'administrators'		=> 'administrators',
			'database'				=> 'panoramastorymap',
			'table'					=> 'scenes',
			'useTemplating'			=> true,
			'apiUsername'			=> true,
			'maptilerApiKey'		=> NULL,
			'assetWidth'			=> '100%',	// Or e.g. '260px'
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available actions
		$actions = array (
			'edit' => array (
				'description' => false,
				'url' => 'edit/',
				'tab' => 'Scenes data',
				'icon' => 'pencil',
				'administrator'	=> true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System administrators';
			
			-- Settings
			CREATE TABLE IF NOT EXISTS `settings` (
			  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Automatic key (ignored)' PRIMARY KEY,
			  `somesetting` varchar(255) NOT NULL COMMENT 'Some setting'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Settings';
			INSERT INTO settings (id) VALUES (1);
			
			-- My scenes
			CREATE TABLE `scenes` (
			  `id` varchar(255) NOT NULL COMMENT 'Scene ID for URL',
			  `title` varchar(255) NOT NULL COMMENT 'Title',
			  `description` text COMMENT 'Description',
			  `lon` decimal(10,8) NOT NULL COMMENT 'Longitude',
			  `lat` decimal(10,8) NOT NULL COMMENT 'Latitude',
			  `sceneFile` varchar(255) NOT NULL COMMENT 'Scene .zip from Marzipano',
			  `assetsFile` varchar(255) NOT NULL COMMENT 'Assets .zip file',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table of scenes';
		";
	}
	
	
	
	# Additional processing
	public function main ()
	{
		
	}
	
	
	
	# Home page
	public function home ()
	{
		# Set the config
		$config = application::arrayFields ($this->settings, array ('maptilerApiKey'));
		$this->template['configJson'] = json_encode ($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to do some action
	public function someaction ()
	{
		//
		$html = __FUNCTION__;
		
		# Show the HTML
		echo $html;
	}
	
	
	# Scenes editor
	public function edit ()
	{
		# Delegate to the standard function for editing
		$sinenomineExtraSettings = array (
			'tableUrlMoniker' => __FUNCTION__,
			'fieldFiltering' => false,
			'callback' => array (
				$this->settings['database'] => array (
					$this->settings['table'] => array ($this, 'processUploadedFiles_callback'),
				),
			),
		);
		$dataBindingAttributes = array (
			'id' => array ('prepend' => '/scenes/', 'append' => '/', 'regexp' => '^[-a-z0-9]+$', 'description' => 'Lower-case a-z, 0-9, hyphens only'),
			'description' => array ('rows' => 3, ),
			'sceneFile' =>  array ('directory' => $this->applicationRoot . '/scenes/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('zip'), ),
			'assetsFile' => array ('directory' => $this->applicationRoot . '/assets/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('zip'), 'description' => 'Images etc. zipped up without folders, with their filenames exactly matching text in the scenes'),
		);
		$this->template['html'] = $this->editingTable ($this->settings['table'], $dataBindingAttributes, 'graybox lines', false, $sinenomineExtraSettings);
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Callback function to process submitted data
	#!# Currently no support in sinenomine for callbacks when deleting a record, needed in this application to delete the asset files
	public function processUploadedFiles_callback ($record, &$errorHtml = '')
	{
		# Handle each file, e.g. uploaded /scenes/my-scene.zip (with no containing directory in the .zip file) is unzipped to /scenes/my-scene/<files.ext...>
		#!# NB This is done before the sinenomine.php rename on line 1484, so causes a warning there; need to handle this scenario better
		$types = array ('scenes' => 'sceneFile', 'assets' => 'assetsFile');
		foreach ($types as $type => $field) {
			
			# Determine path components
			$directory = $this->applicationRoot . "/{$type}/";
			$newDirectory = $directory . $record['id'] . '/';
			$archivedDirectory = $directory . $record['id'] . '.replacedAt-' . date ('Ymd-His') . '/';
			$filename = $record['id'] . '.zip';
			
			# Archive off any folder from a previous version
			if (is_dir ($newDirectory)) {
				rename ($newDirectory, $archivedDirectory);
			}
			
			# Create the directory
			umask (0);
			mkdir ($newDirectory, 0775);
			
			# Move the zip file into the new directory
			rename ($directory . $filename, $newDirectory . $filename);
			
			# Unzip the file; zip folders should not have a top-level folder
			application::unzip ($filename, $newDirectory, $deleteAfterUnzipping = false);
		}
		
		# Attach the assets and links
		$this->attachAssets ($record['id']);
		
		# Return the record (required by the sinenomine callback specification), unmodified
		return $record;
	}
	
	
	# Function to rewrite asset into the references in the scene data file
	private function attachAssets ($id)
	{
		# Create a backup copy of the original scene data file before rewriting it
		$dataFile = $this->applicationRoot . '/scenes/' . $id . '/app-files/data.js';
		if (!file_exists ($dataFile . '.original')) {
			copy ($dataFile, $dataFile . '.original');
		}
		
		# Open the scene file
		$js = file_get_contents ($dataFile);
		
		# Start replacements
		$replacements = array ();
		
		# Create replacements for URLs to become hyperlinks
		$websiteLinkText = 'Website link / Enlace de página web';
		preg_match_all ("@(https?://.+)(?:\s|<|\"|&nbsp;)@U", $js, $matches, PREG_PATTERN_ORDER);
		foreach ($matches[0] as $url) {
			$replacements[$url] =  '<a href="' . $url . '" target="_blank">' . $websiteLinkText . '</a>';
		}
		
		# Standardise the assets file extensions and get the file list
		$assetsDirectory = $this->applicationRoot . '/assets/' . $id . '/';
		$files = directories::standardiseFileExtensions ($assetsDirectory);
		
		# Create a replacement HTML tag for each file, e.g. foo.mp4 is turned into a <video> tag
		foreach ($files as $file) {
			
			# Create HTML tag for each file
			$filename = pathinfo ($file, PATHINFO_BASENAME);
			$path = $this->baseUrl . '/assets/' . $id . '/' . $filename;
			switch (pathinfo ($file, PATHINFO_EXTENSION )) {
				case 'mp4':
					$html = '<video style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="video/mp4" /></video>';
					break;
				case 'm4a':
					$html = '<audio style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="audio/x-m4a" /></video>';
					break;
				case 'mp3':
					$html = '<audio style="width: ' . $this->settings['assetWidth'] . '; display: block;" controls="controls"><source src="' . htmlspecialchars ($path) . '" type="audio/mpeg" /></video>';
					break;
				case 'jpg':
				case 'jpeg':
				case 'png':
					$html = '<img style="width: ' . $this->settings['assetWidth'] . '; display: block;" src="' . htmlspecialchars ($path) . '" />';
					break;
				case 'url':
					$contents = file_get_contents ($file);
					preg_match ('/URL=(.+)$/', $contents, $matches);
					$url = $matches[1];
					$html = '<a href="' . htmlspecialchars ($url) . '" target="_blank" title="[Link opens in a new window]">' . $websiteLinkText . '</a>';
					break;
			}
			
			# Register replacement
			$replacements[$filename] = $html;
		}
		//application::dumpData ($replacements);
		
		# Convert replacments to escape " as the strings in the file are "-quoted
		foreach ($replacements as $filename => $html) {
			$replacements[$filename] = str_replace ('"', '\\"', $html);
		}
		
		# Replace filenames with tags, in the data file
		$js = strtr ($js, $replacements);
		
		# Save the file
		file_put_contents ($dataFile, $js);
	}
	
	
	# API call for locations
	public function apiCall_locations ()
	{
		# Get location data
		$locations = $this->databaseConnection->select ($this->settings['database'], $this->settings['table']);
		
		# Fix up decimal columns
		#!# This needs to be available as an option in FCA passed through to database.php
		foreach ($locations as $id => $location) {
			$locations[$id]['lon'] = (float) $location['lon'];
			$locations[$id]['lat'] = (float) $location['lat'];
		}
		
		# Convert to GeoJSON
		$geojson = array ('type' => 'FeatureCollection', 'features' => array ());
		foreach ($locations as $location) {
			$geojson['features'][] = array (
				'type' => 'Feature',
				'geometry' => array (
					'type' => 'Point',
					'coordinates' => array ($location['lon'], $location['lat']),
				),
				'properties' => application::arrayFields ($location, array ('id', 'title', 'description')),
			);
		}
		
		# Return the data
		return $geojson;
	}
}

?>
