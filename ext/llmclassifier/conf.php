<?php
/**
 * LLM Document Classifier Extension Configuration
 *
 * This file defines the extension metadata and configuration options
 * that appear in the SeedDMS Extension Manager.
 *
 * @author     Andreas Krause
 * @copyright  2026 Andreas Krause
 * @license    GPL-2.0+
 * @package    SeedDMS
 * @subpackage Extensions
 * @version    1.0.0
 */

$EXT_CONF['llmclassifier'] = array(

    // =========================================================================
    // Extension Metadata
    // =========================================================================

    'title' => 'LLM Document Classifier',
    'description' => 'Automatically classifies PDF documents using AI. Analyzes document content to suggest names, assign categories, and add keywords.',
    'disable' => false,
    'version' => '1.0.0',
    'releasedate' => '2026-01-04',
    'author' => array(
        'name' => 'Andreas Krause',
        'email' => '',
        'company' => ''
    ),

    // =========================================================================
    // Configuration Options
    // =========================================================================

    'config' => array(

        // -----------------------------------------------------------------
        // Core Settings
        // -----------------------------------------------------------------

        'llm_enabled' => array(
            'title' => 'Enable LLM Classification',
            'type' => 'checkbox',
        ),

        'llm_endpoint' => array(
            'title' => 'LLM API Endpoint',
            'type' => 'input',
            'size' => 250,
            'placeholder' => 'https://api.openai.com/v1 or https://<resource>.openai.azure.com',
        ),

        'llm_api_key' => array(
            'title' => 'API Key',
            'type' => 'password',
            'size' => 100,
        ),

        'llm_model' => array(
            'title' => 'Model / Deployment Name',
            'type' => 'input',
            'size' => 100,
            'placeholder' => 'gpt-4o, llama3.2, or Azure deployment name',
        ),

        'llm_api_version' => array(
            'title' => 'API Version (Azure only)',
            'type' => 'input',
            'size' => 100,
            'placeholder' => '2024-02-15-preview',
        ),

        // -----------------------------------------------------------------
        // Scope Settings
        // -----------------------------------------------------------------

        'limit_folder' => array(
            'title' => 'Limit Extension to Folder',
            'type' => 'select',
            'internal' => 'folders',
            'allow_empty' => true,
        ),

        'default_category' => array(
            'title' => 'Default Category',
            'type' => 'select',
            'internal' => 'categories',
            'allow_empty' => true,
        ),

        // -----------------------------------------------------------------
        // Classification Settings
        // -----------------------------------------------------------------

        'max_title_length' => array(
            'title' => 'Maximum Title Length',
            'type' => 'input',
            'size' => 10,
            'placeholder' => '100',
        ),

        'restrict_keywords' => array(
            'title' => 'Restrict to Configured Keywords',
            'type' => 'checkbox',
        ),

        // -----------------------------------------------------------------
        // Technical Settings
        // -----------------------------------------------------------------

        'pdftotext_path' => array(
            'title' => 'Path to pdftotext',
            'type' => 'input',
            'size' => 150,
            'placeholder' => '/usr/bin/pdftotext',
        ),

        'max_text_length' => array(
            'title' => 'Max Text Length for LLM',
            'type' => 'input',
            'size' => 10,
            'placeholder' => '4000',
        ),

        'additional_prompt' => array(
            'title' => 'Additional Prompt Instructions',
            'type' => 'textarea',
            'rows' => 6,
            'cols' => 200,
        ),
    ),

    // =========================================================================
    // Dependencies
    // =========================================================================

    'constraints' => array(
        'depends' => array(
            'php' => '8.0.0-',
            'seeddms' => '6.0.0-'
        ),
    ),

    // =========================================================================
    // Extension Files
    // =========================================================================

    'icon' => 'icon.svg',
    'changelog' => 'changelog.md',
    'class' => array(
        'file' => 'class.llmclassifier.php',
        'name' => 'SeedDMS_ExtLLMClassifier'
    ),
    'language' => array(
        'file' => 'lang.php',
    ),
);
