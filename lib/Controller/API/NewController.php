<?php

set_time_limit( 180 );

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
 * 'private_tm_key'     => (string) Private Key for MyMemory ( set to new to create a new one )
 *
 */
class NewController extends ajaxController {

    private $project_name;
    private $source_lang;
    private $target_lang;
    private $mt_engine;  //1 default MyMemory
    private $tms_engine;  //1 default MyMemory
    private $private_tm_key;

    private $private_tm_user = null;
    private $private_tm_pass = null;

    protected $api_output = array(
            'status'  => 'FAIL',
            'message' => 'Untraceable error (sorry, not mapped)'
    );

    public function __construct() {

        //limit execution time to 300 seconds
        set_time_limit( 300 );

        parent::__construct();

        //force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
        header( "Connection: close" );

        $filterArgs = array(
                'project_name'   => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'source_lang'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_lang'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'tms_engine'     => array(
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => array( 'default' => 1, 'min_range' => 0 )
                ),
                'mt_engine'      => array(
                        'filter'  => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR,
                        'options' => array( 'default' => 1, 'min_range' => 0 )
                ),
                'private_tm_key' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        if ( !isset( $__postInput[ 'tms_engine' ] ) || is_null( $__postInput[ 'tms_engine' ] ) ) {
            $__postInput[ 'tms_engine' ] = 1;
        }
        if ( !isset( $__postInput[ 'mt_engine' ] ) || is_null( $__postInput[ 'mt_engine' ] ) ) {
            $__postInput[ 'mt_engine' ] = 1;
        }

        foreach ( $__postInput as $key => $val ) {
            $__postInput[ $key ] = urldecode( $val );
        }

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->project_name   = $__postInput[ 'project_name' ];
        $this->source_lang    = $__postInput[ 'source_lang' ];
        $this->target_lang    = $__postInput[ 'target_lang' ];
        $this->tms_engine     = $__postInput[ 'tms_engine' ]; // Default 1 MyMemory
        $this->mt_engine      = $__postInput[ 'mt_engine' ]; // Default 1 MyMemory
        $this->private_tm_key = $__postInput[ 'private_tm_key' ];

        try {
            if ( $this->tms_engine != 0 ) {
                $test_valid_TMS = Engine::getInstance( $this->tms_engine );
            }
            if ( $this->mt_engine != 0 && $this->mt_engine != 1 ) {
                $test_valid_MT = Engine::getInstance( $this->mt_engine );
            }
        } catch ( Exception $ex ) {
            $this->api_output[ 'message' ] = $ex->getMessage();
            Log::doLog( $ex->getMessage() );

            return -1;
        }

        //from api a key is sent and the value is 'new'
        if ( $this->private_tm_key == 'new' ) {

            try {

                $APIKeySrv = new TMSService();

                $newUser = $APIKeySrv->createMyMemoryKey();

                $this->private_tm_user = $newUser->id;
                $this->private_tm_pass = $newUser->pass;

                $this->private_tm_key = array(
                        array(
                                'key'  => $newUser->key,
                                'name' => null,
                                'r'    => true,
                                'w'    => true
                        )
                );

            } catch ( Exception $e ) {

                $this->api_output[ 'message' ] = 'Project Creation Failure';
                $this->api_output[ 'debug' ]   = array( "code" => $e->getCode(), "message" => $e->getMessage() );

                return -1;
            }

        } else {

            //if a string is sent, transform it into a valid array
            if ( !empty( $this->private_tm_key ) ) {
                $this->private_tm_key = array(
                        array(
                                'key'  => $this->private_tm_key,
                                'name' => null,
                                'r'    => true,
                                'w'    => true
                        )
                );
            } else {
                $this->private_tm_key = array();
            }

        }

        //This is only an element, this seems redundant,
        // but if we need more than a key in the next api version we can easily handle them here
        $this->private_tm_key = array_filter( $this->private_tm_key, array( "self", "sanitizeTmKeyArr" ) );

        if ( empty( $_FILES ) ) {
            $this->result[ 'errors' ][] = array( "code" => -1, "message" => "Missing file. Not Sent." );

            return -1;
        }

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    public function doAction() {

        $uploadFile = new Upload();

        try {
            $stdResult = $uploadFile->uploadFiles( $_FILES );
        } catch ( Exception $e ) {
            $stdResult                     = array();
            $this->result                  = array(
                    'errors' => array(
                            array( "code" => -1, "message" => $e->getMessage() )
                    )
            );
            $this->api_output[ 'message' ] = $e->getMessage();
        }

        $arFiles = array();
        foreach ( $stdResult as $input_name => $input_value ) {
            $arFiles[] = $input_value->name;
        }

        //if fileupload was failed this index ( 0 = does not exists )
        $default_project_name = @$arFiles[ 0 ];
        if ( count( $arFiles ) > 1 ) {
            $default_project_name = "MATECAT_PROJ-" . date( "Ymdhi" );
        }

        if ( empty( $this->project_name ) ) {
            $this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
        }

        if ( empty( $this->source_lang ) ) {
            $this->api_output[ 'message' ] = "Missing source language.";
            $this->result[ 'errors' ][]    = array( "code" => -3, "message" => "Missing source language." );
        }

        if ( empty( $this->target_lang ) ) {
            $this->api_output[ 'message' ] = "Missing target language.";
            $this->result[ 'errors' ][]    = array( "code" => -4, "message" => "Missing target language." );
        }

        //ONE OR MORE ERRORS OCCURRED : EXITING
        //for now we sent to api output only the LAST error message, but we log all
        if ( !empty( $this->result[ 'errors' ] ) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1; //exit code
        }

        $cookieDir      = $uploadFile->getDirUploadToken();
        $intDir         = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
        $errDir         = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;
        $response_stack = array();

        foreach ( $arFiles as $file_name ) {
            $ext = FilesStorage::pathinfo_fix( $file_name, PATHINFO_EXTENSION );


            $conversionHandler = new ConversionHandler();
            $conversionHandler->setFileName( $file_name );
            $conversionHandler->setSourceLang( $this->source_lang );
            $conversionHandler->setTargetLang( $this->target_lang );
            $conversionHandler->setCookieDir( $cookieDir );
            $conversionHandler->setIntDir( $intDir );
            $conversionHandler->setErrDir( $errDir );

            $status = array();

            if ( $ext == "zip" ) {
                // this makes the conversionhandler accumulate eventual errors on files and continue
                $conversionHandler->setStopOnFileException( false );

                $fileObjects = $conversionHandler->extractZipFile();
                //call convertFileWrapper and start conversions for each file

                if ( $conversionHandler->uploadError ) {
                    $fileErrors = $conversionHandler->getUploadedFiles();

                    foreach ( $fileErrors as $fileError ) {
                        if ( count( $fileError->error ) == 0 ) {
                            continue;
                        }

                        $brokenFileName = ZipArchiveExtended::getFileName( $fileError->name );

                        /*
                         * TODO
                         * return error code is 2 because
                         *      <=0 is for errors
                         *      1   is OK
                         *
                         * In this case, we raise warnings, hence the return code must be a new code
                         */
                        $this->result[ 'code' ]                      = 2;
                        $this->result[ 'errors' ][ $brokenFileName ] = array(
                                'code'    => $fileError->error[ 'code' ],
                                'message' => $fileError->error[ 'message' ],
                                'debug'   => $brokenFileName
                        );
                    }

                }

                $realFileObjectInfo  = $fileObjects;
                $realFileObjectNames = array_map(
                        array( 'ZipArchiveExtended', 'getFileName' ),
                        $fileObjects
                );

                foreach ( $realFileObjectNames as $i => &$fileObject ) {
                    $__fileName     = $fileObject;
                    $__realFileName = $realFileObjectInfo[ $i ];
                    $filesize       = filesize( $intDir . DIRECTORY_SEPARATOR . $__realFileName );

                    $fileObject               = array(
                            'name' => $__fileName,
                            'size' => $filesize
                    );
                    $realFileObjectInfo[ $i ] = $fileObject;
                }

                $this->result[ 'data' ][ $file_name ] = json_encode( $realFileObjectNames );

                $stdFileObjects = array();

                if ( $fileObjects !== null ) {
                    foreach ( $fileObjects as $fName ) {

                        if ( isset( $fileErrors ) &&
                                isset( $fileErrors->{$fName} ) &&
                                !empty( $fileErrors->{$fName}->error )
                        ) {
                            continue;
                        }

                        $newStdFile       = new stdClass();
                        $newStdFile->name = $fName;
                        $stdFileObjects[] = $newStdFile;

                    }
                } else {
                    $errors = $conversionHandler->getResult();
                    $errors = array_map( array( 'Upload', 'formatExceptionMessage' ), $errors[ 'errors' ] );

                    $this->result[ 'errors' ]      = array_merge( $this->result[ 'errors' ], $errors );
                    $this->api_output[ 'message' ] = "Zip Error";
                    $this->api_output[ 'debug' ]   = $this->result[ 'errors' ];

                    return false;
                }

                /* Do conversions here */
                $converter              = new ConvertFileWrapper( $stdFileObjects, false );
                $converter->intDir      = $intDir;
                $converter->errDir      = $errDir;
                $converter->cookieDir   = $cookieDir;
                $converter->source_lang = $this->source_lang;
                $converter->target_lang = $this->target_lang;
                $converter->doAction();

                $status = $errors = $converter->checkResult();
                if ( count( $errors ) > 0 ) {
//                    $this->result[ 'errors' ] = array_merge( $this->result[ 'errors' ], $errors );
                    $this->result[ 'code' ] = 2;
                    foreach ( $errors as $__err ) {
                        $brokenFileName = ZipArchiveExtended::getFileName( $__err[ 'debug' ] );

                        if ( !isset( $this->result[ 'errors' ][ $brokenFileName ] ) ) {
                            $this->result[ 'errors' ][ $brokenFileName ] = array(
                                    'code'    => $__err[ 'code' ],
                                    'message' => $__err[ 'message' ],
                                    'debug'   => $brokenFileName
                            );
                        }
                    }
                }
            } else {
                $conversionHandler->doAction();

                $this->result = $conversionHandler->getResult();

                if ( $this->result[ 'code' ] < 0 ) {
                    $this->result;
                }

            }
        }

//        /* Do conversions here */
//        $converter              = new ConvertFileWrapper( $stdResult );
//        $converter->intDir      = $uploadFile->getUploadPath();
//        $converter->errDir      = INIT::$CONVERSIONERRORS_REPOSITORY . DIRECTORY_SEPARATOR . $uploadFile->getDirUploadToken();
//        $converter->cookieDir   = $uploadFile->getDirUploadToken();
//        $converter->source_lang = $this->source_lang;
//        $converter->target_lang = $this->target_lang;
//        $converter->doAction();

        $status = array_values( $status );

        if ( !empty( $status ) ) {
            $this->api_output[ 'message' ] = 'Project Conversion Failure';
            $this->api_output[ 'debug' ]   = $status;
            $this->result[ 'errors' ]      = $status;
            Log::doLog( $status );

            return -1;
        }
        /* Do conversions here */

        if ( isset( $this->result[ 'data' ] ) && !empty( $this->result[ 'data' ] ) ) {
            foreach ( $this->result[ 'data' ] as $zipFileName => $zipFiles ) {
                $zipFiles = json_decode( $zipFiles, true );


                $fileNames = array_column( $zipFiles, 'name' );
                $arFiles   = array_merge( $arFiles, $fileNames );
            }
        }

        $newArFiles = array();
        $linkFiles  = scandir( $intDir );

        foreach ( $arFiles as $__fName ) {
            if ( 'zip' == FilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

                $fs = new FilesStorage();
                $fs->cacheZipArchive( sha1_file( $intDir . DIRECTORY_SEPARATOR . $__fName ), $intDir . DIRECTORY_SEPARATOR . $__fName );

                $linkFiles = scandir( $intDir );

                //fetch cache links, created by converter, from upload directory
                foreach ( $linkFiles as $storedFileName ) {
                    //check if file begins with the name of the zip file.
                    // If so, then it was stored in the zip file.
                    if ( strpos( $storedFileName, $__fName ) !== false &&
                            substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName
                    ) {
                        //add file name to the files array
                        $newArFiles[] = $storedFileName;
                    }
                }

            } else { //this file was not in a zip. Add it normally

                if ( file_exists( $intDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                    $newArFiles[] = $__fName;
                }

            }
        }

        $arFiles = $newArFiles;

        $projectManager   = new ProjectManager();
        $projectStructure = $projectManager->getProjectStructure();

        $projectStructure[ 'project_name' ]         = $this->project_name;
        $projectStructure[ 'result' ]               = $this->result;
        $projectStructure[ 'private_tm_key' ]       = $this->private_tm_key;
        $projectStructure[ 'private_tm_user' ]      = $this->private_tm_user;
        $projectStructure[ 'private_tm_pass' ]      = $this->private_tm_pass;
        $projectStructure[ 'uploadToken' ]          = $uploadFile->getDirUploadToken();
        $projectStructure[ 'array_files' ]          = $arFiles; //list of file name
        $projectStructure[ 'source_language' ]      = $this->source_lang;
        $projectStructure[ 'target_language' ]      = explode( ',', $this->target_lang );
        $projectStructure[ 'mt_engine' ]            = $this->mt_engine;
        $projectStructure[ 'tms_engine' ]           = $this->tms_engine;
        $projectStructure[ 'status' ]               = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
        $projectStructure[ 'skip_lang_validation' ] = true;

        $projectManager = new ProjectManager( $projectStructure );
        $projectManager->createProject();

        $this->result = $projectStructure[ 'result' ];

        if ( !empty( $projectStructure[ 'result' ][ 'errors' ] ) ) {
            //errors already logged
            $this->api_output[ 'message' ] = 'Project Creation Failure';
            $this->api_output[ 'debug' ]   = array_values( $projectStructure[ 'result' ][ 'errors' ] );

        } else {

            //everything ok
            $this->api_output[ 'status' ]       = 'OK';
            $this->api_output[ 'message' ]      = 'Success';
            $this->api_output[ 'id_project' ]   = $projectStructure[ 'result' ][ 'id_project' ];
            $this->api_output[ 'project_pass' ] = $projectStructure[ 'result' ][ 'ppassword' ];
        }

    }

    private static function sanitizeTmKeyArr( $elem ) {

        $elem = TmKeyManagement_TmKeyManagement::sanitize( new TmKeyManagement_TmKeyStruct( $elem ) );

        return $elem->toArray();

    }

}
