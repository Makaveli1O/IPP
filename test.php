<?php
/*  *   *   *   *   *   *
Constant declaration
 */

$options = array();

$directory = '.';

$recursive = false;

$parseScript = './parse.php';

$intScript = './interpret.py';

$parseOnly = false;

$intOnly = false;

$jexamxml = '/pub/courses/ipp/jexamxml/jexamxml.jar';
    
$jexamcfg = '/pub/courses/ipp/jexamxml/options';
/*  *   *   *   *   *   *   *   *
 MARK: Test
 @class Test
 Runs tests. Initializes crutial information, and return values.
*    *  *   *   *   *   *   *   */

class Test{
    private $filename;
    private $parseScript;
    private $intScript;
    private $jexamScript;
    private $expectedCode;
    private $msg;
    private $intOnly;
    private $parseOnly;
    
    /*  *   *   *   *   *   *   *   *
     Initialize all information, recieve input arguments etc.
    *    *  *   *   *   *   *   *   */
    public function __construct($filename){
        $this->filename = $filename;
        $this->parseScript = parseCommand()." < $filename.src";
        $this->intScript = interpretCommand()." --input=$filename.in";
        $this->jexamScript = jexamCommand()." tmp $this->filename.out";
        $this->expectedCode = (int)file_get_contents("$filename.rc");

        if($this->intOnly = intOnly())
            $this->intScript = $this->intScript." --source=$filename.src";
        else if(parseOnly())
            $this->parseOnly = true;
    }
    
    /*  *   *   *   *   *   *   *   *
     destroys temporary file.
    *    *  *   *   *   *   *   *   */
    public function __destruct(){
        unlink("tmp");
    }

    /*  *   *   *   *   *   *   *   *
     runs test.
     @return 0 for success, 1 for wrong code and 2 for wrong output.
    *    *  *   *   *   *   *   *   */
    public function run(){
        //run only parser or interpret script.
        if($this->parseOnly){
            //exec -> (exec script, result recieved from rc, and return rc)
            $return = $this->executeParse();
            $rc = $return[0];
            $result = $return[1];
        }elseif($this->intOnly){
            $return = $this->executeInt();
            $rc = $return[0];
            $result = $return[1];
        //run both in order (parser then interpret)
        }else{
            $return = $this->executeParse();
            $rc = $return[0];
            $result = $return[1];
            // if rc of parser is 0 run interpret
            if ($rc === 0) {
                $result =array();//reinitialize to empty
                // run interpret
                $return = $this->executeInt(true);
                $rc = $return[0];
                $result = $return[1];
            }
        }

        //check rc
        if($this->expectedCode !== $rc){
            $this->msg = "Codes does not match:<br> expected: <b class='red'>$this->expectedCode</b>\n<br> recieved: <b class='red'>$rc</b>";
            return 2;
        }elseif($rc !== 0)
            return 0;

        /*  *   *   *   *   *   *   *   *
         check results and compare them with jexam tool.
         diff tool is used for comparing outputs
        *    *  *   *   *   *   *   *   */
        if($this->parseOnly){
            exec($this->jexamScript, $diff, $rc);
            if($rc){
                $this->msg = "<p>";
                $this->msg .= str_replace("\n", "<br/>", htmlspecialchars((implode("\n", $result))));
                $this->msg .= "</p>";
                return 1;
            }
        }else{
            exec("diff $this->filename.out tmp", $diff, $rc);
            if($rc){
                $this->msg = "<p>";
                $this->msg .= str_replace("\n", "<br/>", htmlspecialchars((implode("\n", $result))));
                $this->msg .= "</p>";
                return 1;
            }
        }
        return 0;
    }
    
    /*  *   *   *   *   *   *   *   *
     @function executeParse
     runs parse script and returns its result and rc.
     @return [result]=>return code (array)
    *    *  *   *   *   *   *   *   */
    public function executeParse(){
        exec($this->parseScript, $result, $rc);
        file_put_contents("tmp", implode("\n", $result));
        return array($rc,$result);
    }
    /*  *   *   *   *   *   *   *   *
     @function executeInt
     runs interpret script and returns its result and rc.
     @return [result]=>return code (array)
    *    *  *   *   *   *   *   *   */
    public function executeInt($both=false){
        // for both source must be added
        if($both)
            exec($this->intScript." --source=tmp", $result, $rc);
        else exec($this->intScript, $result, $rc);
        file_put_contents("tmp", implode("\n", $result));
        return array($rc,$result);
    }

    /*  *   *   *   *   *   *   *   *
     @function answer
     @returns answer message
    *    *  *   *   *   *   *   *   */
    public function answer(){
        return $this->msg;
    }
}
    
/*  *   *   *   *   *   *   *   *
 MARK: print_msg
 @function print_msg
 Prints message to stdout
 *    *  *   *   *   *   *   *   */
function print_msg($msg){
    fwrite(STDOUT,$msg);
    return;
}

/*  *   *   *   *   *   *   *   *
 MARK: eprint
 @function eprint
 Prints error msg to stderr
*    *  *   *   *   *   *   *   */
function eprint($err){
    fwrite(STDERR, $err);
    return;
}
/*  *   *   *   *   *   *   *   *
 MARK: argumentValidity
 @function argumentValidity
 Checks validity of given arguments. Prints help message when -h or --help is being used
 Returns 0 when arguments are valid, 1 otherwise.
 *    *  *   *   *   *   *   *   */
function argumentValidity($argc){
    $shortopts = "h";
    $longopts  = array(
        "help",
        "recursive",
        "directory:",
        "parse-script:",
        "int-script:",
        "parse-only",
        "int-only",
        "jexamxml:",
        "jexamcfg:"
        
    );
    $options = getopt($shortopts, $longopts);
    //both --int and --parse cant be used at same time
    if (array_key_exists('parse-only', $options)) {
        if (array_key_exists('int-script', $options) || array_key_exists('int-only', $options)) {
            eprint("ERROR: --int and --pasrse are forbidden to use at same time.");
            exit(10);
        }
    } else if (array_key_exists('int-only', $options)) {
        if (array_key_exists('parse-script', $options) || array_key_exists('parse-only', $options)) {
            eprint("ERROR: --int and --pasrse are forbidden to use at same time.");
            exit(10);
        }
    }
    //globals
    global $directory;
    global $recursive;
    global $parseScript;
    global $intScript;
    global $parseOnly;
    global $intOnly;
    global $jexamxml;
    global $jexamcfg;
    
    foreach (array_keys($options) as $option){
        switch($option){
            case 'h':
                $msg = "Help message: \n*   *   *   *   *   *   *   *   *   *   *   *\nverbose : -h / --help,\n --directory(set directory of tests(directory of script by default)),\n--recursive(search recursively within all subfolders),--parse-script=file(parse script location(parse.php default),--int-script=file(interpret script(interpret.py default))\n--parse-only(do only parsing part)\n--int-only(do only interpreting part)\n--jexamxml=file(JAR file with A7Soft JexamXML(/pub/courses/ipp/jexamxml/jexamxml.jar by default))\n--jeamcfg=file(file with configuration of A7Soft JexamXML (/pub/courses/ipp/jexamxml/options by default))\n  Displays this message\nfunctionality: Testing functionality of both parse.php and interpret.py.*   *   *   *   *   *   *   *   *   *   *   *\n\n";
                if($argc > 2){
                    eprint("ERR 10: -h can be used only alone.\n");
                    exit(10);
                }
                print_msg($msg);
                exit(0);
            case 'help':
                $msg = "Help message: \n*   *   *   *   *   *   *   *   *   *   *   *\nverbose : -h / --help,\n --directory(set directory of tests(directory of script by default)),\n--recursive(search recursively within all subfolders),--parse-script=file(parse script location(parse.php default),--int-script=file(interpret script(interpret.py default))\n--parse-only(do only parsing part)\n--int-only(do only interpreting part)\n--jexamxml=file(JAR file with A7Soft JexamXML(/pub/courses/ipp/jexamxml/jexamxml.jar by default))\n--jeamcfg=file(file with configuration of A7Soft JexamXML (/pub/courses/ipp/jexamxml/options by default))\n  Displays this message\nfunctionality: Testing functionality of both parse.php and interpret.py.*   *   *   *   *   *   *   *   *   *   *   *\n\n";
                if($argc > 2){
                    eprint("ERR 10: --help can be used only alone.\n");
                    exit(10);
                }
                print_msg($msg);
                exit(0);
            case 'directory':
                if (!is_dir($options['directory']) || !is_readable($options['directory'])){
                    eprint("ERROR: File problems.(does not exist or isn't readable.");
                    exit(41);
                }
                else
                    $directory = $options['directory'];
                break;
            case 'recursive':
                $recursive = true;
                break;
            case 'int-script':
                if (!is_readable($options['int-script']) || !is_file($options['int-script'])){
                    eprint("ERROR: File problems.(does not exist or isn't readable.");
                    exit(41);
                }
                $intScript = $options['int-script'];
                break;
            case 'parse-script':
                if (!is_readable($options['parse-script']) || !is_file($options['parse-script'])){
                    eprint("ERROR: File problems.(does not exist or isn't readable.");
                    exit(41);
                }
                $parseScript = $options['parse-script'];
                break;
            case 'parse-only':
                $parseOnly = true;
                break;
            case 'int-only':
                $intOnly = true;
                break;
            case 'jexamxml':
                if (!is_readable($options['jexamxml']) || !preg_match('/.*\.jar/', $options['jexamxml'])){
                    eprint("ERROR: File problems.(does not exist(isnt in correct format) or isn't readable.");
                    exit(41);
                }
                $jexamxml = $options['jexamxml'];
                break;
            case 'jexamcfg':
                if (!is_readable($options['jexamcfg']) || !is_file($options['jexamcfg'])){
                    eprint("ERROR: File problems.(does not exist or isn't readable.");
                    exit(41);
                }
                $jexamcfg = $options['jexamxml'];
                break;
        }
    }
}
/*  *   *   *   *   *   *   *   *   *  *   *   *   *   *   *   *   *
        FUNCTIONS FOR SENDING INFORMATION TO TEST CLASS
 *    *  *   *   *   *   *   *  *  *    *  *   *   *   *   *   *   */
/*  *   *   *   *   *   *   *   *
 MARK: commandline Commands
 @function parseCommand
 returns @string command for running parse script
 *    *  *   *   *   *   *   *   */
function parseCommand(){
    global $parseScript;
    return 'php ' . $parseScript;
}

/*  *   *   *   *   *   *   *   *
 MARK: commandline Commands
 @function interpretCommand
 returns @string command for running interpret script
 *    *  *   *   *   *   *   *   */
function interpretCommand(){
    global $intScript;
    return 'python3 ' . $intScript;
}

/*  *   *   *   *   *   *   *   *
 MARK: commandline Commands
 @function jexamCommand
 returns @string command for running jexamxml
 *    *  *   *   *   *   *   *   */
function jexamCommand(){
    global $jexamxml;
    return 'java -jar ' . $jexamxml;
}
/*  *   *   *   *   *   *   *   *
 MARK: intOnly
 @function intOnly
 returns @bool if --int-only was set
 *    *  *   *   *   *   *   *   */
function intOnly(){
    global $intOnly;
    return $intOnly;
}
/*  *   *   *   *   *   *   *   *
 MARK: intOnly
 @function intOnly
 returns @bool if --int-only was set
 *    *  *   *   *   *   *   *   */
function parseOnly(){
    global $parseOnly;
    return $parseOnly;
}
/*  *   *   *   *   *   *   *   *
 MARK: findTests
 @functionfindsTests
 find tests and store them in list
 @return
 *    *  *   *   *   *   *   *   */
function findTests($recursive, $directory){
    #global $i_test;

    /*use recursive iterator tool for finding folders and files*/
    if ($recursive){
        $items = new RecursiveDirectoryIterator($directory);
        $items = new RecursiveIteratorIterator($items);
    }else{
        $items = new ArrayIterator(scandir($directory));
    }
    $tests = [];
    $i = 0;
    foreach ($items as $file) {
        // only .src files include source we want, push it into found
        if (preg_match('/(.*)\.src/', $file, $found)) {
            if ($recursive)
                $name = $found[1];
            else
                $name = rtrim($directory, '/') . '/' . $found[1];
            //if file is not readable skip it
            if (!is_readable("$name.src"))
                continue;

            /*
             all test must contain also:
                name.rc(return rc) (if not create one containing 0)
                name.in
                name.out
             */
            if (!is_file("$name.rc")){
                file_put_contents("$name.rc", "0");
            }
            //else if (! is_readable("$name.rc")) {
            //    continue;
            //}
            if (!is_file("$name.in"))
                file_put_contents("$name.in", "");
            if (!is_file("$name.out"))
                file_put_contents("$name.out", "");
            //mark down processed tests
            #$tests[$name] = $i++;
            array_push($tests, $name);
        }
    }
    #$i_test = 0;
    return $tests;
}
    
/*  *   *   *   *   *   *   *   *
 MARK: getPercentage
 @return percentage of success
 *    *  *   *   *   *   *   *   */
function getPercentage($ok, $count){
    return round((count($ok) / $count) * 100, 2);
}
    
/*MAIN - EXECUTION*/
//results arrays
$ok = []; //success code
$failCode = []; //different return code
$failReturn = [];  //different output
    
argumentValidity($argc);
#$i_test = 0; // test process counter
$tests = findTests($recursive, $directory);
$count = 0; //$counting tests
//main loop
foreach ($tests as $i => $file) {
    $test = new Test($file);
    $result = $test->run();
    //append corresponding array
    if ($result == 0)//ok
        array_push($ok, $file);
    elseif($result == 1)//bad output
        $failReturn[$file] = $test->answer();
    elseif($result == 2)//bad return code
        $failCode[$file] = $test->answer();
    $count++;
}
/*  *   *   *   *   *   *   *   *
    HTML OUTPUT GENERATING
 *    *  *   *   *   *   *   *   */
$percentage = 0;
if($count != 0){
    $percentage = getPercentage($ok, $count);
}
//set div class (color) according to resuls
if ($percentage >= 80.00)
    $color = 'green';
else if ($percentage >= 70.00)
    $color = 'purple';
else if ($percentage >= 35.00 && $percentage < 50.00)
    $color = 'orange';
else if ($percentage < 35.00)
    $color = 'red';


    /** generate body */
    $body = "<div class='title'>\n<h1>\nResult:";

    $body .= "  <span class='";
    $body .= $color;
    $body .= "'>$percentage%</span>\n";
    $body .= "</h1>\n";
    $body .= "</div>\n";
    $body .= "<div class=''>\n";
    $body .= "<div class='align-mid'><h2>Overall summary:</h2>\n";
    $body .= "<h3><ul>\n";
    $body .= "<li class='purple'>Number of tests: " . $count . "</li>\n";
    $body .= "<li class='green'>Success: " . count($ok) . "</li>\n";
    $body .= "<li class='red'>Bad code: " . count($failCode) . "</li>\n";
    $body .= "<li class='red'>Bad output: " . count($failReturn) . "</li>\n";
    $body .= "</ul></h3></div>\n";
    $body .= "<h2 class='align-mid'>Graph representation:</h2>";
    $body .= "<div class='chart'><div class='ok green'>.</div></div>\n";
    $body .= "<div class='section' style={width:50%;}><h3 class='green align-mid'>Successful tests</h3>\n";
    $body .= "<div class='flex'>\n";

   //generate successfull tests
    foreach ($ok as $test) {
        $body .= "<div>\n";
        $body .= "<h4 class='green'>$test</h4>";
        $body .= "</div>";
    }

    $body .= "</div></div>\n";

    $body .= "<div class='section align-mid'><h3 class='red'>Failed tests (rc)</h3>\n";
    $body .= "<div class='flex'>\n";
    //generate failed tests
    foreach ($failCode as $test => $message) {
        $body .= "<div>\n";
        $body .= "<h4 class='red'>$test</h4>";
        $body .= "<p>$message</p>";
        $body .= "</div>";
    }
    $body .= "</div>\n";
    $body .= "<h3 class='align-mid red'>Failed tests (output)</h3>\n";
    $body .= "<div class='flex'>\n";
    //generate failed tests (output)
    foreach ($failReturn as $test => $message) {
        $body .= "<div>\n";
        $body .= "<h4 class='red'>$test</h4>";
        $body .= "<p>Your output is: </p>";
        $body .= "<b>$message</b>";
        $body .= "</div>";
    }

    $body .= "</div></div>\n";

    //css
    $style = "<style>\n";
    $style .= ".chart{width:50%;background:red;padding:0px;margin:0 auto;}\n";
    $style .= ".ok{width:".$percentage."%; background:#109c00}\n";
    $style .= ".align-mid{\n";
    $style .= "    text-align:center;";
    $style .= "}\n";
    $style .= "h1{\n";
    $style .= "    text-align: center;\n";
    $style .= "    background: #fff;\n";
    $style .= "}\n";
    $style .= "h4{margin-top:0;margin-bottom:0;padding: 2px;}\n";
    $style .= ".purple{color: #8B008B;}\n";
    $style .= ".green{color: #109c00;}\n";
    $style .= ".red{color: #cf0000;}\n";
    $style .= ".orange{color: #d6d600;}\n";
    $style .= ".pointer{cursor: pointer;}\n";
    $style .= "ul{\n";
    $style .= "list-style-type: none;\n";
    $style .= "padding: 0;\n";
    $style .= "}";
    $style .= ".summary{\n";
    $style .= "list-style-type: none;\n";
    $style .= "padding: 0;\n";
    $style .= "}";
    $style .= ".section{\n";
    $style .= "margin: 10px 0;\n";
    $style .= "box-shadow: 0px 0px 4px #afafaf;\n";
    $style .= "padding: 10px;\n";
    $style .= "background: whitesmoke;\n";
    $style .= "}";
    $style .= "li{margin: 5px 0;\n}";
    $style .= ".flex{\n";
    $style .= "display: flex;\n";
    $style .= "flex-wrap: wrap;\n";
    $style .= "}";
    $style .= ".flex > div{\n";# all the divs directly within .flex class
    $style .= "flex: 0 0 calc(33.33% - 40px);\n";
    $style .= "margin: 5px;\n";
    $style .= "padding: 5px;\n";
    $style .= "text-align: center;\n";
    $style .= "background: whitesmoke;\n";
    $style .= "max-width: calc(33.33% - 40px);\n";
    $style .= "word-break: break-all;\n";
    $style .= "}\n";
    $style .= "</style>\n";
    
    $html = "<!DOCTYPE html>\n";
    $html .= "<html lang='cs'>\n";
    $html .= "<head>\n";
    $html .= "\t<meta charset='UTF-8'>\n";
    $html .= "\t<title>Tests</title>\n";
    $html .= $style;
    $html .= "</head>\n";
    $html .= "<body>\n";
    $html .= $body;
    $html .= "</body>\n";
    $html .= "</html>\n";

    echo $html;
    
?>
