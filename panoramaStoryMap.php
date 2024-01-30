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
			  `sceneFile` varchar(255) DEFAULT NULL COMMENT 'Scene .zip from Marzipano',
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
		);
		$dataBindingAttributes = array (
			'id' => array ('prepend' => '/scenes/', 'append' => '/', 'regexp' => '^[-a-z0-9]+$', 'description' => 'Lower-case a-z, 0-9, hyphens only'),
			'description' => array ('rows' => 3, ),
			'sceneFile' => array ('directory' => $this->applicationRoot . '/scenes/', 'forcedFileName' => '%id', 'lowercaseExtension' => true, 'allowedExtensions' => array ('zip'), ),
		);
		$this->template['html'] = $this->editingTable ('scenes', $dataBindingAttributes, 'graybox lines', false, $sinenomineExtraSettings);
		
		# Process the template
		$html = $this->templatise ();
		
		# Show the HTML
		echo $html;
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
