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
		'usesContext' => array(
			'ace/formId',
			'ace/appearance',
			'ace/fieldGroup'
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
			'association' => array(
				'type' => 'object',
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
			),
			'acf' => array(
				'type' => 'object',
				'default' => array(

				)
			),
			'storage' => array(
				'type' => 'string',
				'default' => 'native'
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
	'field-group' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/field-group',
		'version' => '0.500.0',
		'title' => 'Field group',
		'category' => 'widgets',
		'textdomain' => 'adaptive-content-enhancer',
		'attributes' => array(
			'groupKey' => array(
				'type' => 'string',
				'default' => ''
			),
			'location' => array(
				'type' => 'array',
				'default' => array(
					array(
						array(
							'param' => 'post_type',
							'operator' => '==',
							'value' => 'post'
						)
					)
				)
			),
			'presentation' => array(
				'type' => 'string',
				'default' => 'sidebar'
			)
		),
		'providesContext' => array(
			'ace/fieldGroup' => 'groupKey'
		),
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css'
	),
	'field-repeater' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/field-repeater',
		'version' => '0.500.0',
		'title' => 'Repeater or layout',
		'category' => 'widgets',
		'textdomain' => 'adaptive-content-enhancer',
		'ancestor' => array(
			'adaptive-content-enhancer/field-group'
		),
		'attributes' => array(
			'key' => array(
				'type' => 'string',
				'default' => ''
			),
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'type' => array(
				'type' => 'string',
				'default' => 'repeater'
			),
			'acf' => array(
				'type' => 'object',
				'default' => array(

				)
			),
			'storage' => array(
				'type' => 'string',
				'default' => 'native'
			)
		),
		'supports' => array(
			'html' => false
		),
		'editorScript' => 'file:./index.js'
	),
	'form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/form',
		'version' => '0.500.0',
		'title' => 'Form',
		'category' => 'widgets',
		'description' => 'A form with fields edited directly in the page.',
		'textdomain' => 'adaptive-content-enhancer',
		'attributes' => array(
			'formId' => array(
				'type' => 'string',
				'default' => ''
			),
			'name' => array(
				'type' => 'string',
				'default' => 'Contact form'
			),
			'settings' => array(
				'type' => 'object',
				'default' => array(

				)
			),
			'appearance' => array(
				'type' => 'object',
				'default' => array(
					'labels' => 'floating'
				)
			)
		),
		'providesContext' => array(
			'ace/formId' => 'formId',
			'ace/appearance' => 'appearance'
		),
		'supports' => array(
			'html' => false,
			'spacing' => true,
			'color' => true,
			'typography' => true
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'form-step' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'adaptive-content-enhancer/form-step',
		'version' => '0.500.0',
		'title' => 'Form step',
		'category' => 'widgets',
		'description' => 'A page of fields within a multi-step form.',
		'textdomain' => 'adaptive-content-enhancer',
		'ancestor' => array(
			'adaptive-content-enhancer/form'
		),
		'attributes' => array(
			'title' => array(
				'type' => 'string',
				'default' => 'Step'
			),
			'description' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => true
		),
		'editorScript' => 'file:./index.js',
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
