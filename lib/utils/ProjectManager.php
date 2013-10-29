<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 22/10/13
 * Time: 17.25
 *
 */

class ProjectManager {

    protected $projectStructure;

    protected $mysql_link;

    public function __construct( ArrayObject $projectStructure = null ){

        if ( $projectStructure == null ) {
            $projectStructure = new RecursiveArrayObject(
                array(
                    'id_project'         => null,
                    'id_customer'        => null,
                    'user_ip'            => null,
                    'project_name'       => null,
                    'result'             => null,
                    'private_tm_key'     => 0,
                    'private_tm_user'    => null,
                    'private_tm_pass'    => null,
                    'array_files'        => array(), //list of file names
                    'file_id_list'       => array(),
                    'source_language'    => null,
                    'target_language'    => null,
                    'mt_engine'          => null,
                    'tms_engine'         => null,
                    'ppassword'          => null,
                    'array_jobs'         => array( 'job_list' => array(), 'job_pass' => array(),'job_segments' => array() ),
                    'job_segments'       => array(), //array of job_id => array( min_seg, max_seg )
                    'segments'           => array(), //array of files_id => segmentsArray()
                    'translations'       => array(), //one translation for every file because translations are files related
                    'query_translations' => array(),
                    'status'             => 'NOT_READY_FOR_ANALYSIS',
                    'job_to_split'       => null,
                    'split_result'       => null,
                ) );
        }

        $this->projectStructure = $projectStructure;

        $mysql_hostname = INIT::$DB_SERVER;   // Database Server machine
        $mysql_database = INIT::$DB_DATABASE;     // Database Name
        $mysql_username = INIT::$DB_USER;   // Database User
        $mysql_password = INIT::$DB_PASS;

        $this->mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
        mysql_select_db($mysql_database, $this->mysql_link);
        
    }

    public function getProjectStructure(){
        return $this->projectStructure;
    }

    public function createProject() {

        // aggiungi path file in caricamento al cookie"pending_upload"a
        // add her the cookie mangement for remembere the last 3 choosed languages
        // project name sanitize
        $this->projectStructure['project_name'] = preg_replace( '/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $this->projectStructure['project_name'] );
        $this->projectStructure['project_name'] = preg_replace( '/[_]{2,}/', "_", $this->projectStructure['project_name'] );
        $this->projectStructure['project_name'] = str_replace( '_.', ".", $this->projectStructure['project_name'] );

        // project name validation
        $pattern = '/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u';
        if ( !preg_match( $pattern, $this->projectStructure['project_name'], $rr ) ) {
            $this->projectStructure['result'][ 'errors' ][ ] = array( "code" => -5, "message" => "Invalid Project Name " . $this->projectStructure['project_name'] . ": it should only contain numbers and letters!" );
            return false;
        }

        // create project
        $this->projectStructure['ppassword']   = $this->_generatePassword();
        $this->projectStructure['user_ip']     = Utils::getRealIpAddr();
        $this->projectStructure['id_customer'] = 'translated_user';

        $this->projectStructure['id_project'] = insertProject( $this->projectStructure );

        //create user (Massidda 2013-01-24)
        //this is done only if an API key is provided
        if ( !empty( $this->projectStructure['private_tm_key'] ) ) {
            //the base case is when the user clicks on "generate private TM" button:
            //a (user, pass, key) tuple is generated and can be inserted
            //if it comes with it's own key without querying the creation api, create a (key,key,key) user
            if ( empty( $this->projectStructure['private_tm_user'] ) ) {
                $this->projectStructure['private_tm_user'] = $this->projectStructure['private_tm_key'];
                $this->projectStructure['private_tm_pass'] = $this->projectStructure['private_tm_key'];
            }

            insertTranslator( $this->projectStructure );

        }


        $uploadDir = INIT::$UPLOAD_REPOSITORY . "/" . $_COOKIE['upload_session'];
        foreach ( $this->projectStructure['array_files'] as $fileName) {

            /**
             * Conversion Enforce
             *
             * Check Extension no more sufficient, we want check content
             * if this is an idiom xlf file type, conversion are enforced
             * $enforcedConversion = true; //( if conversion is enabled )
             */
            $enforcedConversion = false;
            try {

                $fileType = DetectProprietaryXliff::getInfo( INIT::$UPLOAD_REPOSITORY. '/' .$_COOKIE['upload_session'].'/' . $fileName );
                //Log::doLog( 'Proprietary detection: ' . var_export( $fileType, true ) );

                if( $fileType['proprietary'] == true  ){

                    if( INIT::$CONVERSION_ENABLED && $fileType['proprietary_name'] == 'idiom world server' ){
                        $enforcedConversion = true;
                        Log::doLog( 'Idiom found, conversion Enforced: ' . var_export( $enforcedConversion, true ) );

                    } else {
                        /**
                         * Application misconfiguration.
                         * upload should not be happened, but if we are here, raise an error.
                         * @see upload.class.php
                         * */
                        $this->projectStructure['result']['errors'][] = array("code" => -8, "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($fileName)");
                        setcookie("upload_session", "", time() - 10000);
                        return;
                        //stop execution
                    }
                }
            } catch ( Exception $e ) { Log::doLog( $e->getMessage() ); }


            $mimeType = pathinfo( $fileName , PATHINFO_EXTENSION );

            $original_content = "";
            if ( ( ( $mimeType != 'sdlxliff' && $mimeType != 'xliff' && $mimeType != 'xlf' ) || $enforcedConversion ) && INIT::$CONVERSION_ENABLED ) {

                //converted file is inside "_converted" directory
                $fileDir = $uploadDir . '_converted';
                $original_content = file_get_contents("$uploadDir/$fileName");
                $sha1_original = sha1($original_content);
                $original_content = gzdeflate($original_content, 5);

                //file name is a xliff converted like: 'a_word_document.doc.sdlxliff'
                $real_fileName = $fileName . '.sdlxliff';

            } else {

                //filename is already an xliff and it is in a canonical normal directory
                $sha1_original = "";
                $fileDir = $uploadDir;
                $real_fileName = $fileName;
            }

            $filePathName = $fileDir . '/' . $real_fileName;

            if ( !file_exists( $filePathName ) ) {
                $this->projectStructure[ 'result' ][ 'errors' ][ ] = array( "code" => -6, "message" => "File not found on server after upload." );
            }

            $contents = file_get_contents($filePathName);

            try{

                $fid = insertFile( $this->projectStructure, $fileName, $mimeType, $contents, $sha1_original, $original_content );
                $this->projectStructure[ 'file_id_list' ]->append( $fid );

                $this->_extractSegments( $filePathName, $fid );

            } catch ( Exception $e ){

                if ( $e->getCode() == -1 ) {
                    $this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "No segments found in your XLIFF file. ($fileName)");
                } else if( $e->getCode() == -2 ) {
                    $this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Not able to import this XLIFF file. ($fileName)");
                } else {
                    //mysql insert Blob Error
                    $this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Not able to import this XLIFF file. ($fileName)");
                }

                return false;

            }
            //exit;
        }

        //Log::doLog( array_pop( array_chunk( $SegmentTranslations[$fid], 25, true ) ) );
        //create job

        if (isset($_SESSION['cid']) and !empty($_SESSION['cid'])) {
            $owner = $_SESSION['cid'];
        } else {
            $_SESSION['_anonym_pid'] = $this->projectStructure['id_project'];
            //default user
            $owner = '';
        }

        //TODO Throws exception
        try {
            $this->_createJobs( $this->projectStructure, $owner );
        } catch ( Exception $ex ){
            $this->projectStructure['result']['errors'][] = array( "code" => -9, "message" => "Fail to create Job. ( {$ex->getMessage()} )" );
            return false;
        }

        self::_deleteDir( $uploadDir );
        if ( is_dir( $uploadDir . '_converted' ) ) {
            self::_deleteDir( $uploadDir . '_converted' );
        }

        $this->projectStructure['status'] = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? 'NEW' : 'NOT_TO_ANALYZE';
        changeProjectStatus( $this->projectStructure['id_project'], $this->projectStructure['status'] );
        $this->projectStructure['result'][ 'code' ]            = 1;
        $this->projectStructure['result'][ 'data' ]            = "OK";
        $this->projectStructure['result'][ 'ppassword' ]       = $this->projectStructure['ppassword'];
        $this->projectStructure['result'][ 'password' ]        = $this->projectStructure['array_jobs']['job_pass'];
        $this->projectStructure['result'][ 'id_job' ]          = $this->projectStructure['array_jobs']['job_list'];
        $this->projectStructure['result'][ 'job_segments' ]    = $this->projectStructure['array_jobs']['job_segments'];
        $this->projectStructure['result'][ 'id_project' ]      = $this->projectStructure['id_project'];
        $this->projectStructure['result'][ 'project_name' ]    = $this->projectStructure['project_name'];
        $this->projectStructure['result'][ 'source_language' ] = $this->projectStructure['source_language'];
        $this->projectStructure['result'][ 'target_language' ] = $this->projectStructure['target_language'];

    }

    protected function _createJobs( ArrayObject $projectStructure, $owner ) {

        foreach ( $projectStructure['target_language'] as $target ) {

            $query_min_max = "SELECT MIN( id ) AS job_first_segment , MAX( id ) AS job_last_segment
                                FROM segments WHERE id_file IN ( %s )";

            $string_file_list = implode( "," , $projectStructure['file_id_list']->getArrayCopy() );
            $last_segments_query = sprintf( $query_min_max, $string_file_list );
            $res = mysql_query( $last_segments_query, $this->mysql_link );

            if (!$res) {
                Log::doLog("Segment Search: Failed Retrieve min_segment/max_segment for files ( $string_file_list ) - DB Error: " . mysql_error() . " - \n");
                throw new Exception( "Segment import - DB Error: " . mysql_error(), -5);
            }

            //IT IS EVERY TIME ONLY A LINE!! don't worry about a cycle
            $job_segments = mysql_fetch_assoc( $res );

            $password = $this->_generatePassword();
            $jid = insertJob( $projectStructure, null, $password, $target, $job_segments, $owner );
            $projectStructure['array_jobs']['job_list']->append( $jid );
            $projectStructure['array_jobs']['job_pass']->append( $password );
            $projectStructure['array_jobs']['job_segments']->offsetSet( $jid . "-" . $password, $job_segments );

            foreach ( $projectStructure['file_id_list'] as $fid ) {

                try {
                    //prepare pre-translated segments queries
                    if( !empty( $projectStructure['translations'] ) ){
                        $this->_insertPreTranslations( $jid );
                    }
                } catch ( Exception $e ) {
                    $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
                    Utils::sendErrMailReport($msg);
                }

                insertFilesJob($jid, $fid);

            }

        }

    }

    /**
     *
     * Build a job split structure, minimum split value are 2 chunks
     *
     * @param ArrayObject $projectStructure
     * @param int         $num_split
     *
     * @return RecursiveArrayObject
     *
     * @throws Exception
     */
    public function getSplitData( ArrayObject $projectStructure, $num_split = 2 ) {

        $num_split = (int)$num_split;

        if( $num_split < 2 ){
            throw new Exception( 'Minimum Chunk number for split is 2.' );
        }

        /**
         * Select all rows raw_word_count and eq_word_count
         * and their totals ( ROLLUP )
         * reserve also two columns for job_first_segment and job_last_segment as NULL values
         * Economize bytes by returning placeholders with null values
         *
         * The UNION add a select with the same number of columns as the previous query
         * but with first 3 columns as null
         *
         * +----------------+-------------------+---------+-------------------+------------------+
         * | raw_word_count | eq_word_count     | id      | job_first_segment | job_last_segment |
         * +----------------+-------------------+---------+-------------------+------------------+
         * |             10 |               8.5 | 2386093 |              NULL |             NULL |
         * |              4 |               1.2 | 2386094 |              NULL |             NULL |
         * |              0 |                 0 | 2386095 |              NULL |             NULL |
         * |              0 |                 0 | 2386096 |              NULL |             NULL |
         * |             14 |     9.70000000002 |    NULL |              NULL |             NULL |  ---- ROLLUP ROW
         * |           NULL |              NULL |    NULL |           2386093 |          2386096 |  ---- UNION  ROW
         * +----------------+-------------------+---------+-------------------+------------------+
         *
         */
        $query = "SELECT * FROM (

                        SELECT
                            SUM(raw_word_count) AS raw_word_count,
                            SUM(eq_word_count) AS eq_word_count,
                            seg.id,
                            NULL AS job_first_segment, NULL AS job_last_segment
                        FROM segments AS seg
                        JOIN files_job USING (id_file)
                        JOIN jobs ON jobs.id = files_job.id_job
                        LEFT JOIN segment_translations AS ts
                                ON seg.id = ts.id_segment
                                AND jobs.id = ts.id_job
                        WHERE jobs.id = %u
                        GROUP BY seg.id WITH ROLLUP

                        UNION

                        SELECT NULL, NULL, NULL, job_first_segment, job_last_segment
                        FROM jobs
                        WHERE jobs.id = %u
                        AND jobs.password = '%s'

                  ) AS results";

        $query = sprintf( $query,
                            $projectStructure[ 'job_to_split' ],
                            $projectStructure[ 'job_to_split' ],
                            $projectStructure[ 'job_to_split_pass' ]
        );
        $res   = mysql_query( $query, $this->mysql_link );

        //assignment in condition is often dangerous, deprecated
        while ( ( $rows[] = mysql_fetch_assoc( $res ) ) != false );
        array_pop( $rows ); //destroy last assignment row ( every time === false )

        if( empty( $rows ) ){
            throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ] );
        }

        $row_union     = array_slice( array_pop( $rows ), 3, null, true ); //get the last row ( UNION )
        $row_totals    = array_slice( array_pop( $rows ), 0, 2, true    ); //get the last row ( ROLLUP )
        $row_pivot     = array_merge( $row_totals, $row_union );

        //if fast analysis with equivalent word count is present
        $count_type    = ( !empty( $row_totals[ 'eq_word_count' ] ) ? 'eq_word_count' : 'raw_word_count' );
        $total_words   = $row_totals[ $count_type ];

        $words_per_job = round( $total_words / $num_split, 0, PHP_ROUND_HALF_DOWN );

        $counter = array();
        $chunk   = 0;
        foreach( $rows as $row ) {

            if( !array_key_exists( $chunk, $counter ) ){
                $counter[$chunk] = array(
                    'eq_word_count'  => 0,
                    'raw_word_count' => 0,
                    'segment_start'  => $row['id'],
                    'segment_end'    => 0,
                );
            }

            $counter[$chunk][ 'eq_word_count' ]  += $row[ 'eq_word_count' ];
            $counter[$chunk][ 'raw_word_count' ] += $row[ 'raw_word_count' ];
            $counter[$chunk][ 'segment_end' ]     = $row[ 'id' ];

            //check for wanted words per job
            if( $counter[$chunk][ $count_type ] >= $words_per_job ){
                $chunk++;
            }

        }

        $result = array_merge( $row_pivot, array( 'chunks' => $counter ) );

        $projectStructure['split_result'] = new RecursiveArrayObject( $result );

        return $projectStructure['split_result'];

    }

    protected function _cloneJob( ArrayObject $projectStructure ){

        $query_job = "SELECT * FROM jobs WHERE id = %u AND password = '%s'";
        $query_job = sprintf( $query_job, $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );
        //$projectStructure[ 'job_to_split' ]

        $jobInfo = mysql_query( $query_job, $this->mysql_link );
        $jobInfo = mysql_fetch_assoc( $jobInfo );

        $data = array();

        foreach( $projectStructure['split_result']['chunks'] as $chunk => $contents ){

            //IF THIS IS NOT the original job
            if( $contents['segment_start'] != $projectStructure['split_result']['job_first_segment'] ){
                //next insert
                $jobInfo['password'] =  $this->_generatePassword();
                $jobInfo['last_opened_segment'] = null;
                $jobInfo['create_date']  = date('Y-m-d H:i:s');
            }

            $jobInfo['job_first_segment'] = $contents['segment_start'];
            $jobInfo['job_last_segment']  = $contents['segment_end'];

            $query = "INSERT INTO jobs ( " . implode( ", ", array_keys( $jobInfo ) ) . " )
                        VALUES ( '" . implode( "', '", array_values( $jobInfo ) ) . "' )
                        ON DUPLICATE KEY UPDATE
                            job_first_segment = '{$jobInfo['job_first_segment']}',
                            job_last_segment = '{$jobInfo['job_last_segment']}' ";


            //add here job id to list
            $projectStructure['array_jobs']['job_list']->append( $projectStructure[ 'job_to_split' ] );
            //add here passwords to list
            $projectStructure['array_jobs']['job_pass']->append( $jobInfo['password'] );

            $projectStructure['array_jobs']['job_segments']->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $jobInfo['password'], new ArrayObject( array( $contents['segment_start'], $contents['segment_end'] ) ) );

            $data[] = $query;
        }

        foreach( $data as $query ){
            $res = mysql_query( $query, $this->mysql_link );
            if( $res !== true ){
                $msg = "Failed to split job into " . count( $projectStructure['split_result']['chunks'] ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r(  $data ,true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
                Utils::sendErrMailReport( $msg );
                throw new Exception( 'Failed to insert job chunk, project damaged.' );
            }
        }

    }

    public function applySplit( ArrayObject $projectStructure ){
        $this->_cloneJob( $projectStructure );
    }

    protected function _extractSegments( $files_path_name, $fid ){

        $info = pathinfo( $files_path_name );

        //create Structure fro multiple files
        $this->projectStructure['segments']->offsetSet( $fid, new ArrayObject( array() ) );

        // Checking Extentions
        if (($info['extension'] == 'xliff') || ($info['extension'] == 'sdlxliff') || ($info['extension'] == 'xlf')) {
            $content = file_get_contents( $files_path_name );
        } else {
            throw new Exception( "Failed to find Xliff - no segments found", -3 );
        }

        $xliff_obj = new Xliff_Parser();
        $xliff = $xliff_obj->Xliff2Array($content);

        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            Log::doLog( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
            throw new Exception( "Xliff Import: Error parsing. Check Log file.", -4 );
        }

        // Creating the Query
        foreach ($xliff['files'] as $xliff_file) {

            if (!array_key_exists('trans-units', $xliff_file)) {
                continue;
            }

            foreach ($xliff_file['trans-units'] as $xliff_trans_unit) {
                if (!isset($xliff_trans_unit['attr']['translate'])) {
                    $xliff_trans_unit['attr']['translate'] = 'yes';
                }
                if ($xliff_trans_unit['attr']['translate'] == "no") {

                } else {
                    // If the XLIFF is already segmented (has <seg-source>)
                    if (isset($xliff_trans_unit['seg-source'])) {

                        foreach ($xliff_trans_unit['seg-source'] as $position => $seg_source) {

                            $show_in_cattool = 1;
                            $tempSeg = strip_tags($seg_source['raw-content']);
                            $tempSeg = trim($tempSeg);

                            //init tags
                            $seg_source['mrk-ext-prec-tags'] = '';
                            $seg_source['mrk-ext-succ-tags'] = '';

                            if ( is_null($tempSeg) || $tempSeg === '' ) {
                                $show_in_cattool = 0;
                            } else {
                                $extract_external = $this->_strip_external($seg_source['raw-content']);
                                $seg_source['mrk-ext-prec-tags'] = $extract_external['prec'];
                                $seg_source['mrk-ext-succ-tags'] = $extract_external['succ'];
                                $seg_source['raw-content'] = $extract_external['seg'];

                                if( isset( $xliff_trans_unit['seg-target'][$position]['raw-content'] ) ){
                                    $target_extract_external = $this->_strip_external( $xliff_trans_unit['seg-target'][$position]['raw-content'] );

                                    //we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                    //AND IF IT IS ONLY A CHAR? like "*" ?
                                    //we can't distinguish if it is translated or not
                                    //this means that we lose the tags id inside the target if different from source
                                    $src = strip_tags( html_entity_decode( $extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );
                                    $trg = strip_tags( html_entity_decode( $target_extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );

                                    if( $src != $trg ){

                                        $target = CatUtils::placeholdnbsp($target_extract_external['seg']);
                                        $target = mysql_real_escape_string($target);

                                        //add an empty string to avoid casting to int: 0001 -> 1
                                        //useful for idiom internal xliff id
                                        $this->projectStructure['translations']->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ] , $target );

                                        //seg-source and target translation can have different mrk id
                                        //override the seg-source surrounding mrk-id with them of target
                                        $seg_source['mrk-ext-prec-tags'] = $target_extract_external['prec'];
                                        $seg_source['mrk-ext-succ-tags'] = $target_extract_external['succ'];

                                    }

                                }

                            }

                            //Log::doLog( $xliff_trans_unit ); die();

                            $seg_source[ 'raw-content' ] = CatUtils::placeholdnbsp( $seg_source[ 'raw-content' ] );

                            $mid                   = mysql_real_escape_string( $seg_source[ 'mid' ] );
                            $ext_tags              = mysql_real_escape_string( $seg_source[ 'ext-prec-tags' ] );
                            $source                = mysql_real_escape_string( $seg_source[ 'raw-content' ] );
                            $ext_succ_tags         = mysql_real_escape_string( $seg_source[ 'ext-succ-tags' ] );
                            $num_words             = CatUtils::segment_raw_wordcount( $seg_source[ 'raw-content' ] );
                            $trans_unit_id         = mysql_real_escape_string( $xliff_trans_unit[ 'attr' ][ 'id' ] );
                            $mrk_ext_prec_tags     = mysql_real_escape_string( $seg_source[ 'mrk-ext-prec-tags' ] );
                            $mrk_ext_succ_tags     = mysql_real_escape_string( $seg_source[ 'mrk-ext-succ-tags' ] );
                            $this->projectStructure['segments'][$fid]->append( "('$trans_unit_id',$fid,'$source',$num_words,'$mid','$ext_tags','$ext_succ_tags',$show_in_cattool,'$mrk_ext_prec_tags','$mrk_ext_succ_tags')" );

                        }

                    } else {
                        $show_in_cattool = 1;

                        $tempSeg = strip_tags( $xliff_trans_unit['source']['raw-content'] );
                        $tempSeg = trim($tempSeg);
                        $tempSeg = CatUtils::placeholdnbsp( $tempSeg );
                        $prec_tags = NULL;
                        $succ_tags = NULL;
                        if ( empty( $tempSeg ) || $tempSeg == NBSPPLACEHOLDER ) { //@see cat.class.php, ( DEFINE NBSPPLACEHOLDER ) don't show <x id=\"nbsp\"/>
                            $show_in_cattool = 0;
                        } else {
                            $extract_external                              = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
                            $prec_tags                                     = empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
                            $succ_tags                                     = empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
                            $xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

                            if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                                if ( $xliff_trans_unit[ 'source' ][ 'raw-content' ] != $target_extract_external[ 'seg' ] ) {

                                    $target = CatUtils::placeholdnbsp( $target_extract_external[ 'seg' ] );
                                    $target = mysql_real_escape_string( $target );

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    $this->projectStructure['translations']->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ], new ArrayObject( array( 2 => $target ) ) );

                                }

                            }
                        }

                        $source = CatUtils::placeholdnbsp( $xliff_trans_unit['source']['raw-content'] );

                        //we do the word count after the place-holding with <x id="nbsp"/>
                        //so &nbsp; are now not recognized as word and not counted as payable
                        $num_words = CatUtils::segment_raw_wordcount($source);

                        //applying escaping after raw count
                        $source = mysql_real_escape_string($source);

                        $trans_unit_id = mysql_real_escape_string($xliff_trans_unit['attr']['id']);

                        if (!is_null($prec_tags)) {
                            $prec_tags = mysql_real_escape_string($prec_tags);
                        }
                        if (!is_null($succ_tags)) {
                            $succ_tags = mysql_real_escape_string($succ_tags);
                        }
                        $this->projectStructure['segments'][$fid]->append( "('$trans_unit_id',$fid,'$source',$num_words,NULL,'$prec_tags','$succ_tags',$show_in_cattool,NULL,NULL)" );

                    }
                }
            }
        }

        // *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
        //use generic

        if (empty($this->projectStructure['segments'][$fid])) {
            Log::doLog("Segment import - no segments found\n");
            throw new Exception( "Segment import - no segments found", -1 );
        }

        $baseQuery = "INSERT INTO segments ( internal_id, id_file, segment, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values ";

        Log::doLog( "Total Rows to insert: " . count( $this->projectStructure['segments'][$fid] ) );
        //split the query in to chunks if there are too much segments
        $this->projectStructure['segments'][$fid]->exchangeArray( array_chunk( $this->projectStructure['segments'][$fid]->getArrayCopy(), 1000 ) );

        Log::doLog( "Total Queries to execute: " . count( $this->projectStructure['segments'][$fid] ) );


        foreach( $this->projectStructure['segments'][$fid] as $i => $chunk ){

            $res = mysql_query( $baseQuery . join(",\n", $chunk ) , $this->mysql_link);
            Log::doLog( "Executed Query " . ( $i+1 ) );
            if (!$res) {
                Log::doLog("Segment import - DB Error: " . mysql_error() . " - \n");
                throw new Exception( "Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
            }

        }

        //Log::doLog( $this->projectStructure );

        if( !empty( $this->projectStructure['translations'] ) ){

            $last_segments_query = "SELECT id, internal_id from segments WHERE id_file = %u";
            $last_segments_query = sprintf( $last_segments_query, $fid );

            $last_segments = mysql_query( $last_segments_query, $this->mysql_link );

            //assignment in condition is often dangerous, deprecated
            while ( ( $row = mysql_fetch_assoc( $last_segments ) ) != false ) {

                if( $this->projectStructure['translations']->offsetExists( "" . $row['internal_id'] ) ) {
                    $this->projectStructure['translations'][ "" . $row['internal_id'] ]->offsetSet( 0, $row['id'] );
                    $this->projectStructure['translations'][ "" . $row['internal_id'] ]->offsetSet( 1, $row['internal_id'] );
                }

            }

        }

    }

    protected function _insertPreTranslations( $jid ){

//    Log::doLog( array_shift( array_chunk( $SegmentTranslations, 5, true ) ) );

        foreach ( $this->projectStructure['translations'] as $internal_id => $struct ){

            if( empty($struct) ) {
//            Log::doLog( $internal_id . " : " . var_export( $struct, true ) );
                continue;
            }

            //id_segment, id_job, status, translation, translation_date, tm_analysis_status, locked
            $this->projectStructure['query_translations']->append( "( '{$struct[0]}', $jid, 'TRANSLATED', '{$struct[2]}', NOW(), 'DONE', 1 )" );

        }

        // Executing the Query
        if( !empty( $this->projectStructure['query_translations'] ) ){

            $baseQuery = "INSERT INTO segment_translations (id_segment, id_job, status, translation, translation_date, tm_analysis_status, locked)
            values ";

            Log::doLog( "Total Rows to insert: " . count( $this->projectStructure['query_translations'] ) );
            //split the query in to chunks if there are too much segments
            $this->projectStructure['query_translations']->exchangeArray( array_chunk( $this->projectStructure['query_translations']->getArrayCopy(), 1000 ) );

            Log::doLog( "Total Queries to execute: " . count( $this->projectStructure['query_translations'] ) );

//        Log::doLog( print_r( $query_translations,true ) );

            foreach( $this->projectStructure['query_translations'] as $i => $chunk ){

                $res = mysql_query( $baseQuery . join(",\n", $chunk ) , $this->mysql_link);
                Log::doLog( "Executed Query " . ( $i+1 ) );
                if (!$res) {
                    Log::doLog("Segment import - DB Error: " . mysql_error() . " - \n");
                    throw new Exception( "Translations Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
                }

            }

        }

        //clean translations and queries
        $this->projectStructure['query_translations']->exchangeArray( array() );
        $this->projectStructure['translations']->exchangeArray( array() );

    }

    protected function _generatePassword( $length = 16 ){
        return CatUtils::generate_password( $length );
    }

    protected function _strip_external( $a ) {
        $a               = str_replace( "\n", " NL ", $a );
        $pattern_x_start = '/^(\s*<x .*?\/>)(.*)/mis';
        $pattern_x_end   = '/(.*)(<x .*?\/>\s*)$/mis';
        $pattern_g       = '/^(\s*<g [^>]*?>)([^<]*?)(<\/g>\s*)$/mis';
        $found           = false;
        $prec            = "";
        $succ            = "";

        $c = 0;

        do {
            $c += 1;
            $found = false;

            do {
                $r = preg_match_all( $pattern_x_start, $a, $res );
                if ( isset( $res[ 1 ][ 0 ] ) ) {
                    $prec .= $res[ 1 ][ 0 ];
                    $a     = $res[ 2 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 1 ][ 0 ] ) );

            do {
                $r = preg_match_all( $pattern_x_end, $a, $res );
                if ( isset( $res[ 2 ][ 0 ] ) ) {
                    $succ  = $res[ 2 ][ 0 ] . $succ;
                    $a     = $res[ 1 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 2 ][ 0 ] ) );

            do {
                $r = preg_match_all( $pattern_g, $a, $res );
                if ( isset( $res[ 1 ][ 0 ] ) ) {
                    $prec .= $res[ 1 ][ 0 ];
                    $succ  = $res[ 3 ][ 0 ] . $succ;
                    $a     = $res[ 2 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 1 ][ 0 ] ) );

        } while ( $found );
        $prec = str_replace( " NL ", "\n", $prec );
        $succ = str_replace( " NL ", "\n", $succ );
        $a    = str_replace( " NL ", "\n", $a );
        $r    = array( 'prec' => $prec, 'seg' => $a, 'succ' => $succ );

        return $r;
    }
    
    protected static function _deleteDir( $dirPath ) {
        return true;
        $iterator = new DirectoryIterator( $dirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() ) continue;
            if ( $fileInfo->isDir() ) {
                self::_deleteDir( $fileInfo->getPathname() );
            } else {
                unlink( $fileInfo->getPathname() );
            }
        }
        rmdir( $iterator->getPath() );

    }

}