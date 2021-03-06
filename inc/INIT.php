<?php


class INIT {

    public static $ROOT;
    public static $BASEURL;
    public static $HTTPHOST;
    public static $PROTOCOL;
    public static $DEBUG = true;
    public static $EXCEPTION_DEBUG = false;
    public static $DB_SERVER;
    public static $DB_DATABASE;
    public static $DB_USER;
    public static $DB_PASS;
    public static $MEMCACHE_SERVERS = array();
    public static $REDIS_SERVERS = array();
    public static $QUEUE_BROKER_ADDRESS;
    public static $QUEUE_DQF_ADDRESS;
    public static $QUEUE_JMX_ADDRESS;
    public static $USE_COMPILED_ASSETS = false;

    public static $QUEUE_NAME = "matecat_analysis_queue";
    //This queue will be used for dqf project creation
    public static $DQF_PROJECTS_TASKS_QUEUE_NAME = "matecat_dqf_project_task_queue";
    //This queue will be used for dqf project creation
    public static $DQF_SEGMENTS_QUEUE_NAME = "matecat_dqf_segment_queue";

    public static $COMMENTS_ENABLED = true ;
    public static $SSE_COMMENTS_QUEUE_NAME = "matecat_sse_comments";
    public static $SSE_BASE_URL;

    public static $SMTP_HOST;
    public static $SMTP_PORT;
    public static $SMTP_SENDER;
    public static $SMTP_HOSTNAME;

    public static $MAILER_FROM = 'cattool@matecat.com' ;
    public static $MAILER_FROM_NAME = 'MateCat';
    public static $MAILER_RETURN_PATH = 'no-reply@matecat.com';

    public static $LOG_REPOSITORY;
    public static $STORAGE_DIR;
    public static $UPLOAD_REPOSITORY;
    public static $FILES_REPOSITORY;
    public static $CACHE_REPOSITORY;
    public static $ZIP_REPOSITORY;
    public static $CONVERSIONERRORS_REPOSITORY;
    public static $CONVERSIONERRORS_REPOSITORY_WEB;
    public static $TMP_DOWNLOAD;
    public static $TEMPLATE_ROOT;
    public static $MODEL_ROOT;
    public static $CONTROLLER_ROOT;
    public static $UTILS_ROOT;
    public static $DEFAULT_NUM_RESULTS_FROM_TM;
    public static $THRESHOLD_MATCH_TM_NOT_TO_SHOW;
    public static $TIME_TO_EDIT_ENABLED;
    public static $DEFAULT_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES;
    public static $CONVERSION_FILE_TYPES_PARTIALLY_SUPPORTED;
    public static $AUTHSECRET;
    public static $AUTHSECRET_PATH;
    public static $REFERENCE_REPOSITORY;
    public static $DQF_ENABLED = false;


    public static $LONGHORN_SERVER = false;
    public static $LONGHORN_OFFICE_SERVER_URL;
    public static $LONGHORN_OFFICE_SERVER_PORT;
    public static $LONGHORN_ANT_PATH;

    public static $LONGHORN_PIPELINE_PATH;
    public static $LONGHORN_SEGMENTATION_PATH;
    public static $LONGHORN_CONFIGURATION_PATH;


    public static $FORCE_XLIFF_CONVERSION    = false;
    public static $VOLUME_ANALYSIS_ENABLED   = true;
    public static $WARNING_POLLING_INTERVAL  = 20; //seconds
    public static $SEGMENT_QA_CHECK_INTERVAL = 1; //seconds
    public static $SAVE_SHASUM_FOR_FILES_LOADED = true;
    public static $AUTHCOOKIENAME = 'matecat_login_v2';
    public static $SUPPORT_MAIL = 'the owner of this Matecat instance';//default string is 'the owner of this Matecat instance'
    public static $ANALYSIS_WORDS_PER_DAYS = 3000;
    public static $AUTHCOOKIEDURATION = 5184000;            // 86400 * 60;         // seconds
    public static $MAX_UPLOAD_FILE_SIZE = 62914560;         // 60 * 1024 * 1024;  // bytes
    public static $MAX_UPLOAD_TMX_FILE_SIZE = 104857600;    // 100 * 1024 * 1024; // bytes
    public static $MAX_NUM_FILES = 100;

    public static $CONFIG_VERSION_ERR_MESSAGE = "Your config.ini file is not up-to-date.";

    /**
     * This interval is needed for massive copy-source-to-target feature. <br>
     * If user triggers that feature 3 times within this interval (in seconds),
     * a popup appears asking him if he wants to trigger the massive function.
     * @var int Interval in seconds
     */
    public static $COPY_SOURCE_INTERVAL = 300;

    /**
     * Default Matecat user agent string
     */
    const MATECAT_USER_AGENT = 'Matecat-Cattool/v';

    /**
     * @const JOB_ARCHIVABILITY_THRESHOLD int number of days of inactivity for a job before it's automatically archived
     */
    const JOB_ARCHIVABILITY_THRESHOLD = 30;

    /**
     * ENABLE_OUTSOURCE set as true will show the option to outsource to an external translation provider (translated.net by default)
     * You can set it to false, but We are happy if you keep this on.
     * For each translation outsourced to Translated.net (the main Matecat developer),
     * Matecat gets more development budget and bugs fixes and new features get implemented faster.
     * In short: please turn it off only if strictly necessary :)
     * @var bool
     */
    public static $ENABLE_OUTSOURCE = true;

    /**
     * Matecat open source by default only handles xliff files with a strong focus on sdlxliff
     * ( xliff produced by SDL Trados )
     *
     * We are not including the file converters into the Matecat code because we haven't find any open source
     * library that satisfy the required quality and licensing.
     *
     * Here you have two options
     *  a) Keep $CONVERSION_ENABLED to false, manually convert your files into xliff using SDL Trados, Okapi or similar
     *  b) Set $CONVERSION_ENABLED to true and implement your own converter
     *
     */
    public static $CONVERSION_ENABLED = false;

    /**
     * The MateCat Version
     */
    public static $BUILD_NUMBER;

    /**
     * MyMemory Developer Email Key for the cattool
     * @var string
     */
    public static $MYMEMORY_API_KEY = 'demo@matecat.com' ;

    /**
     * MyMemory Developer Email Key for the analysis
     * @var string
     */
    public static $MYMEMORY_TM_API_KEY = 'tmanalysis@matecat.com' ;


    public static $ENABLED_BROWSERS = array( 'applewebkit', 'chrome', 'safari' ); //, 'firefox');

    // sometimes the browser declare to be Mozilla but does not provide a valid Name (e.g. Safari).
    // This occurs especially in mobile environment. As an example, when you try to open a link from within
    // the GMail app, it redirect to an internal browser that does not declare a valid user agent
    // In this case we will show a notice on the top of the page instead of deny the access
    public static $UNTESTED_BROWSERS = array( 'mozillageneric' );

    /**
     * If you don't have a client id and client secret, please visit
     * Google Developers Console (https://console.developers.google.com/)
     * and follow these instructions:
     * - click "Create Project" button and specify project name
     * - In the sidebar on the left, select APIs & auth.
     * - In the displayed list of APIs, make sure "Google+ API" show a status of ON. If it doesn't, enable it.
     * - In the sidebar on the left, select "Credentials" under "APIs & auth" menu.
     * - Click "Create new client ID" button
     * - under APPLICATION TYPE, select "web application" option
     * - under AUTHORIZED JAVASCRIPT ORIGINS, insert the domain on which you installed MateCat
     * - under REDIRECT URIs, insert "http://<domain>/oauth/response" , where <domain> is the same that you specified in the previous step
     * - click "Create client ID"
     * Your client ID and client secret are now available.
     *
     * Edit the file inc/oauth_config.ini.sample with the right parameters obtained in the previous step of this guide.
     * set:
     * OAUTH_CLIENT_ID with your client ID
     * OAUTH_CLIENT_SECRET with your client secret
     * OAUTH_CLIENT_APP_NAME with your custom app name, if you want, or leave Matecat
     *
     * save and rename to oauth_config.ini file.
     *
     * Done!
     */
    public static $OAUTH_CONFIG;
    public static $OAUTH_CLIENT_ID;
    public static $OAUTH_CLIENT_SECRET;
    public static $OAUTH_CLIENT_APP_NAME;
    public static $OAUTH_REDIRECT_URL;
    public static $OAUTH_SCOPES;

    public function __construct(){

        self::$OAUTH_CLIENT_ID       = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_ID' ];
        self::$OAUTH_CLIENT_SECRET   = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_SECRET' ];
        self::$OAUTH_CLIENT_APP_NAME = INIT::$OAUTH_CONFIG[ 'OAUTH_CLIENT_APP_NAME' ];

        self::$OAUTH_REDIRECT_URL = INIT::$HTTPHOST . "/oauth/response";
        self::$OAUTH_SCOPES       = array(
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile'
        );

    }

    public static $LONGHORN_CONVERTIBLE_EXTENSIONS = array(
            "doc"  => "docx",
            "dot"  => "docx",
            "xls"  => "xlsx",
            "xlt"  => "xlsx",
            "xlsb" => "xlsx",
            "xlw"  => "xlsx",
            "ppt"  => "pptx",
            "pot"  => "pptx",
            "pps"  => "pptx"
    );

    public static $SPELL_CHECK_TRANSPORT_TYPE = 'shell';
    public static $SPELL_CHECK_ENABLED        = false;
    public static $SUPPORTED_FILE_TYPES = array(
            'Office'              => array(
                    'doc'  => array( '', '', 'extdoc' ),
                    'dot'  => array( '', '', 'extdoc' ),
                    'docx' => array( '', '', 'extdoc' ),
                    'dotx' => array( '', '', 'extdoc' ),
                    'docm' => array( '', '', 'extdoc' ),
                    'dotm' => array( '', '', 'extdoc' ),
                    'rtf'  => array( '', '', 'extdoc' ),
                    'odt'  => array( '', '', 'extdoc' ),
                    'sxw'  => array( '', '', 'extdoc' ),
                    'txt'  => array( '', '', 'exttxt' ),
                    'pdf'  => array( '', '', 'extpdf' ),
                    'xls'  => array( '', '', 'extxls' ),
                    'xlt'  => array( '', '', 'extxls' ),
                    'xlsm' => array( '', '', 'extxls' ),
                    'xlsx' => array( '', '', 'extxls' ),
                    'xltx' => array( '', '', 'extxls' ),
                    'ods'  => array( '', '', 'extxls' ),
                    'sxc'  => array( '', '', 'extxls' ),
                    'csv'  => array( '', '', 'extxls' ),
                    'pot'  => array( '', '', 'extppt' ),
                    'pps'  => array( '', '', 'extppt' ),
                    'ppt'  => array( '', '', 'extppt' ),
                    'potm' => array( '', '', 'extppt' ),
                    'potx' => array( '', '', 'extppt' ),
                    'ppsm' => array( '', '', 'extppt' ),
                    'ppsx' => array( '', '', 'extppt' ),
                    'pptm' => array( '', '', 'extppt' ),
                    'pptx' => array( '', '', 'extppt' ),
                    'odp'  => array( '', '', 'extppt' ),
                    'sxi'  => array( '', '', 'extppt' ),
                    'xml'  => array( '', '', 'extxml' ),
                    'zip'  => array( '', '', 'extzip' ),
                //                'vxd' => array("Try converting to XML")
            ),
            'Web'                 => array(
                    'htm'   => array( '', '', 'exthtm' ),
                    'html'  => array( '', '', 'exthtm' ),
                    'xhtml' => array( '', '', 'exthtm' ),
                    'xml'   => array( '', '', 'extxml' )
            ),
            "Interchange Formats" => array(
                    'xliff'    => array( 'default', '', 'extxif' ),
                    'sdlxliff' => array( 'default', '', 'extxif' ),
                    'tmx'      => array( '', '', 'exttmx' ),
                    'ttx'      => array( '', '', 'extttx' ),
                    'itd'      => array( '', '', 'extitd' ),
                    'xlf'      => array( 'default', '', 'extxlf' )
            ),
            "Desktop Publishing"  => array(
                //                'fm' => array('', "Try converting to MIF"),
                    'mif'  => array( '', '', 'extmif' ),
                    'inx'  => array( '', '', 'extidd' ),
                    'idml' => array( '', '', 'extidd' ),
                    'icml' => array( '', '', 'extidd' ),
                //                'indd' => array('', "Try converting to INX"),
                    'xtg'  => array( '', '', 'extqxp' ),
                    'tag'  => array( '', '', 'exttag' ),
                    'xml'  => array( '', '', 'extxml' ),
                    'dita' => array( '', '', 'extdit' )
            ),
            "Localization"        => array(
                    'properties'  => array( '', '', 'extpro' ),
                    'rc'          => array( '', '', 'extrcc' ),
                    'resx'        => array( '', '', 'extres' ),
                    'xml'         => array( '', '', 'extxml' ),
                    'dita'        => array( '', '', 'extdit' ),
                    'sgm'         => array( '', '', 'extsgm' ),
                    'sgml'        => array( '', '', 'extsgm' ),
                    'Android xml' => array( '', '', 'extxml' ),
                    'strings'     => array( '', '', 'extstr' )
            )
    );

    public static $UNSUPPORTED_FILE_TYPES = array(
            'fm'   => array( '', "Try converting to MIF" ),
            'indd' => array( '', "Try converting to INX" )
    );

    /**
     * Initialize the Class Instance
     */
    public static function obtain() {
        new self();
    }

}
