<?php
// This file is generated. Do not modify it manually.
return array(
	'field' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/field',
		'version' => '0.500.0',
		'title' => 'Field',
		'category' => 'widgets',
		'description' => 'A form or custom-data field.',
		'textdomain' => 'adaptive-content-enhancer',
		'ancestor' => array(
			'adaptive-content-enhancer/form',
			'adaptive-content-enhancer/field-group'
		),
		'attributes' => array(
			'type' => array(
				'type' => 'string',
				'default' => 'text'
			),
			'key' => array(
				'type' => 'string',
				'default' => ''
			),
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'help' => array(
				'type' => 'string',
				'default' => ''
			),
			'placeholder' => array(
				'type' => 'string',
				'default' => ''
			),
			'required' => array(
				'type' => 'boolean',
				'default' => false
			),
			'choices' => array(
				'type' => 'array',
				'default' => array(
					
				)
			),
			'defaultValue' => array(
				'type' => 'object',
				'default' => array(
					'source' => 'static',
					'value' => ''
				)
			),
			'rules' => array(
				'type' => 'object',
				'default' => array(
					
				)
			),
			'conditions' => array(
				'type' => 'object',
				'default' => array(
					
				)
			),
			'autocomplete' => array(
				'type' => 'string',
				'default' => ''
			),
			'personal' => array(
				'type' => 'boolean',
				'default' => false
			),
			'width' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true
			),
			'typography' => array(
				'fontSize' => true
			),
			'interactivity' => true
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'submit' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/submit',
		'version' => '0.500.0',
		'title' => 'Submit',
		'category' => 'widgets',
		'textdomain' => 'adaptive-content-enhancer',
		'ancestor' => array(
			'adaptive-content-enhancer/form'
		),
		'attributes' => array(
			'text' => array(
				'type' => 'string',
				'default' => 'Send'
			)
		),
		'supports' => array(
			'html' => false,
			'color' => true,
			'spacing' => true,
			'typography' => true
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	)
);
