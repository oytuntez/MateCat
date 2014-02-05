<?php

//NO More needed
//include_once INIT::$UTILS_ROOT . "/API/Upload.php";
//include_once INIT::$UTILS_ROOT . "/Utils.php";

/**
 *
 * Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol
 *
 * POST Params:
 *
 * 'project_name'       => (string) The name of the project you want create
 * 'source_lang'        => (string) RFC 3066 language Code ( en-US )
 * 'target_lang'        => (string) RFC 3066 language(s) Code. Comma separated ( it-IT,fr-FR,es-ES )
 * 'tms_engine'         => (int)    Identifier for Memory Server ( ZERO means disabled, ONE means MyMemory )
 * 'mt_engine'          => (int)    Identifier for TM Server ( ZERO means disabled, ONE means MyMemory )
 * 'disable_tms_engine' => (bool)   Memory Server should be disabled or not ( Force 'tms_engine' to ZERO )
 * 'private_tm_key'     => (string) Private Key for MyMemory
 * 'create_new_tm_key'  => (bool)   Set true if you want to create a new MyMemory key ( used only if 'private_tm_key' is null )
 *
 *
 */
class NewController extends ajaxController {

    private $project_name;
    private $source_lang;
    private $target_lang;
    private $mt_engine;  //1 default MyMemory
    private $tms_engine;  //1 default MyMemory
    private $private_tm_key;
    private $create_new_tm_key;

    private $private_tm_user = null;
    private $private_tm_pass = null;

    protected $api_output = array(
        'status' => 'FAIL'
    );

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

        $filterArgs = array(
                'project_name'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'source_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'tms_engine'         => array( 'filter' => FILTER_VALIDATE_INT ),
                'mt_engine'          => array( 'filter' => FILTER_VALIDATE_INT ),
                'disable_tms_engine' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
                'private_tm_key'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'create_new_tm_key'  => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        foreach( $__postInput as $key => $val ){
            $__postInput[$key] = urldecode( $val );
        }

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->project_name            = $__postInput[ 'project_name' ];
        $this->source_lang             = $__postInput[ 'source_lang' ];
        $this->target_lang             = $__postInput[ 'target_lang' ];
        $this->tms_engine              = $__postInput[ 'tms_engine' ]; // Default 1 MyMemory
        $this->mt_engine               = $__postInput[ 'mt_engine' ]; // Default 1 MyMemory
        $this->disable_tms_engine_flag = $__postInput[ 'disable_tms_engine' ]; // se false allora MyMemory
        $this->private_tm_key          = $__postInput[ 'private_tm_key' ];
        $this->create_new_tm_key       = $__postInput[ 'create_new_tm_key' ];

        if ( $this->disable_tms_engine_flag ) {
            $this->tms_engine = 0; //remove default MyMemory
        }

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    public function doAction() {

        if( $this->disable_tms_engine_flag ){
            $this->tms_engine = 0;
        }

        try {
            if( $this->tms_engine != 0 ){
                include INIT::$UTILS_ROOT . "/engines/tms.class.php";
                $test_valid_TMS = new TMS( $this->tms_engine );
            }
            if( $this->mt_engine != 0 && $this->mt_engine != 1 ){
                include INIT::$UTILS_ROOT . "/engines/mt.class.php";
                $test_valid_MT = new MT( $this->mt_engine );
            }
        } catch ( Exception $ex ) {
            Log::doLog( $ex->getMessage() );
            return -1;
        }

        if (empty($_FILES)) {
            $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
            return -1;
        }

        $uploadFile = new Upload();

        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch( Exception $e ){
            $stdResult = array();
            $this->result = array(
                    'errors' => array(
                            array( "code" => -1, "message" => $e->getMessage() )
                    )
            );
        }

        $arFiles = array();
        foreach( $stdResult as $input_name => $input_value ){
            $arFiles[] = $input_value->name;
        }

        $default_project_name = $arFiles[0];
        if (count($arFiles) > 1) {
            $default_project_name = "MATECAT_PROJ-" . date("Ymdhi");
        }

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Missing source language." );
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Missing target language." );
        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1; //exit code
        }

        /* Do conversions here */
        $converter = new ConvertFileWrapper( $stdResult );
        $converter->intDir = $uploadFile->getUploadPath();
        $converter->errDir = INIT::$CONVERSIONERRORS_REPOSITORY . DIRECTORY_SEPARATOR . $uploadFile->getDirUploadToken();
        $converter->source_lang = $this->source_lang;
        $converter->target_lang = $this->target_lang;
        $converter->doAction();
        try {
            $converter->checkResult();
        } catch ( Exception $ex ){
            return -1;
        }
        /* Do conversions here */

        //the first check on empty is redundant but assume that
        //from api a key is sent and the flag is check
        if( empty( $this->private_tm_key ) && $this->create_new_tm_key ){
            //crea nuova chiave
            $newUser = TMS::createMyMemoryKey(); //throws exception

            $this->private_tm_key  = $newUser->key;
            $this->private_tm_user = $newUser->id;
            $this->private_tm_pass = $newUser->pass;

        }

        $projectStructure = new RecursiveArrayObject(
                array(
                        'id_project'         => null,
                        'id_customer'        => null,
                        'user_ip'            => null,
                        'project_name'       => $this->project_name,
                        'result'             => $this->result,
                        'private_tm_key'     => $this->private_tm_key,
                        'private_tm_user'    => $this->private_tm_user,
                        'private_tm_pass'    => $this->private_tm_pass,
                        'uploadToken'        => $uploadFile->getDirUploadToken(),
                        'array_files'        => $arFiles, //list of file names
                        'file_id_list'       => array(),
                        'file_references'    => array(),
                        'source_language'    => $this->source_lang,
                        'target_language'    => explode( ',', $this->target_lang ),
                        'mt_engine'          => $this->mt_engine,
                        'tms_engine'         => $this->tms_engine,
                        'ppassword'          => null, //project password
                        'array_jobs'         => array( 'job_list' => array(), 'job_pass' => array(), 'job_segments' => array(  ) ),
                        'job_segments'       => array(), //array of job_id => array( min_seg, max_seg )
                        'segments'           => array(), //array of files_id => segmentsArray()
                        'translations'       => array(), //one translation for every file because translations are files related
                        'query_translations' => array(),
                        'status'             => 'NOT_READY_FOR_ANALYSIS',
                        'job_to_split'       => null,
                        'job_to_split_pass'  => null,
                        'split_result'       => null,
                ) );

        $projectManager = new ProjectManager( $projectStructure );
        $projectManager->createProject();

        $this->result = $projectStructure['result'];

        if( !empty( $projectStructure['result']['errors'] ) ){
            //errors already logged
            return -1;
        }

        $this->api_output[ 'status' ]       = 'OK';
        $this->api_output[ 'id_project' ]   = $projectStructure['result']['id_project'];
        $this->api_output[ 'ppassword' ]    = $projectStructure['result']['ppassword'];

    }

}