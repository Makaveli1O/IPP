<?php
    /*  *   *   *   *   *   *   *   *
    Constants & auxiliary variables
    *    *  *   *   *   *   *   *   */
    ini_set('display_errors', 'stderr');
    /* error codes */
    const badArgument = 10;
    const badSourceHeader = 21;
    const badOpcode = 22;
    const badSourceCode = 23;
    
    /* flags */
    $i_count = 0;
    $header = false;
    $fflag = 0;
    $frame_flag; // 1-CREATEFRAME, 2, PUSHFRAME
    $aux = 0;
    $arg1;$arg2;$arg3;
    $h_flag = false;
    
    /*  *   *   *   *   *   *   *   *
     @function print_msg
     Prints message to stdout
     *    *  *   *   *   *   *   *   */
    function print_msg($msg){
        fwrite(STDOUT,$msg);
        return;
    }
    
    /*  *   *   *   *   *   *   *   *
     @function print_err
     Prints error msg to stderr
    *    *  *   *   *   *   *   *   */
    function print_err($err){
        fwrite(STDERR, $err);
        return;
    }
    /*  *   *   *   *   *   *   *   *
     @function argumentValidity
     Checks validity of given arguments. Prints help message when -h or --help is being used
     Returns 0 when arguments are valid, 1 otherwise.
     *    *  *   *   *   *   *   *   */
    function argumentValidity($argc){
        $shortopts = "h";
        $longopts  = array(
            "help"
        );
        $opts = getopt($shortopts, $longopts);

        foreach (array_keys($opts) as $opt){
            switch($opt){
                case 'h':
                    $msg = "Help message: \n*   *   *   *   *   *   *   *   *   *   *   *\nverbose : -h / --help Displays this message\nfunctionality: Reads from stdin IPP-code21, checks its lexical and syntactic correctness, \nand prints its XML representation of program. Run with 'php7.4 parse.php'\n*   *   *   *   *   *   *   *   *   *   *   *\n\n";
                    if($argc > 2){
                        print_err("ERR 10: -h can be used only alone.\n");
                        exit(10);
                    }
                    print_msg($msg);
                    exit(0);
                case 'help':
                    $msg = "Help message: \n*   *   *   *   *   *   *   *   *   *   *   *\nverbose : -h / --help Displays this message\nfunctionality: Reads from stdin IPP-code21, checks its lexical and syntactic correctness, \nand prints its XML representation of program. Run with 'php7.4 parse.php'\n*   *   *   *   *   *   *   *   *   *   *   *\n\n";
                    if($argc > 2){
                        print_err("ERR 10: --help can be used only alone.\n");
                        exit(10);
                    }
                    print_msg($msg);
                    exit(0);
              }
        }
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion syntaxAnalysis
     Creates DOM tree, and runs syntax analysis for
     given IPP21 code
    *    *  *   *   *   *   *   *   */
    function syntaxAnalysis(){
        /*create basic skeleton*/
        global $i_count, $header, $fflag, $aux, $arg1, $arg2, $arg3;
        $DOM = new DOMDocument('1.0', 'UTF-8');
        $DOM->formatOutput = true;
        $program = NULL;

        while($line = fgets(STDIN)){
            $retval = scanner($line); //parse line
            switch($retval){
                case 1: //head
                    $program = $DOM->createElement("program");
                    $program->setAttribute("language", "IPPcode21");
                    $DOM->appendChild($program);
                    break;
                case 2: //RETURN
                    createNode('RETURN', $DOM, $program);
                    break;
                case 3: //BREAK
                    createNode('BREAK', $DOM, $program);
                    break;
                case 4: //CREATEFRAME
                    createNode('CREATEFRAME', $DOM, $program);
                    break;
                case 5: //PUSHFRAME
                    createNode('PUSHFRAME', $DOM, $program);
                    break;
                case 6: //POPFRAME
                    createNode('POPFRAME', $DOM, $program);
                    break;
                case 7: //DEFVAR
                    $node = $DOM->createElement("instruction");
                    $node->setAttribute("order", $i_count);
                    $node->setAttribute("opcode", "DEFVAR");
                    $program->appendChild($node);
                    $arg = $DOM->createElement("arg1", htmlspecialchars($arg1));
                    $arg->setAttribute("type", "var");
                    $node->appendChild($arg);
                    break;
                case 8://MOVE , INT2CHAR <var> <symb>
                    createVarSymbNode($header, $DOM, $program);
                    break;
                case 9://CALL, LABEL, JUMP <label>
                    createLabelNode($header, $DOM, $program);
                    break;
                case 10: //PUSHS, WRITE, DPRINT, EXIT <symb>
                    createSymbNode($header, $DOM, $program);
                    break;
                case 11: //POPS <var>
                    $node = $DOM->createElement("instruction");
                    $node->setAttribute("order", $i_count);
                    $node->setAttribute("opcode", "POPS");
                    $program->appendChild($node);
                    $argnode1 = $DOM->createElement("arg1", $arg1);
                    $argnode1->setAttribute("type", "var");
                    $node->appendChild($argnode1);
                    break;
                case 12: // CONCAT, SETCHAR etc ..<var> <symb1> <symb2>
                    createAritmeticNode3($header, $DOM,$program);
                    break;
                case 13:// JUMPNEQ ... <label> <symb1> <symb2>
                    createLabelSymb2($header, $DOM, $program);
                    break;
                case 14: //READ <var> <type>
                    $node = $DOM->createElement("instruction");
                    $node->setAttribute("order", $i_count);
                    $node->setAttribute("opcode", "READ");
                    $program->appendChild($node);
                    $argnode1 = $DOM->createElement("arg1", $arg1);
                    $argnode1->setAttribute("type", "var");
                    $node->appendChild($argnode1);
                    $argnode2 = $DOM->createElement("arg2", $arg2);
                    $argnode2->setAttribute("type", "type");
                    $node->appendChild($argnode2);
                    break;
                /* ERRORS */
                case -21:
                    fprintf(STDERR, "ERR 21: chybná nebo chybějící hlavička ve zdrojovém kódu zapsaném v IPPcode21\n");
                    exit(badSourceHeader);
                case -22:
                    fprintf(STDERR, "ERR 22: neznámý nebo chybný operační kód ve zdrojovém kódu zapsaném v IPPcode21\n");
                    exit(badOpcode);
                case -23:
                    fprintf(STDERR, "ERR 23: jiná lexikální nebo syntaktická chyba zdrojového kódu zapsaného v IPPcode21.\n");
                    exit(badSourceCode);
            }
        }
        
        print($DOM->saveXML());
        return True;
    }
    /*  *   *   *   *   *   *   *   *
     @funcion scanner
     Scans input, and checks its correctness. 1 line
     should represent 1 instruction.
     @return
    *    *  *   *   *   *   *   *   */
    function scanner($line){
        $instruction = "#";
        global $i_count, $frame_flag, $header, $fflag, $arg1, $arg2, $arg3, $h_flag, $aux;

        $line = trim($line, "\n");
        
        $line = parseComments($line);
        $line_split = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        //if its empty, next condition will catch it
        if(!empty($line_split[0]))
            $instruction = $line_split[0];
        //comments or blank lines
        if(preg_match("/#.*/",$instruction)){
            return;
        }
        /* first istruction must be .IPPcode21, error 21 otherwise */
        if($h_flag == false){
            if(strtoupper($instruction) == ".IPPCODE21" && $i_count == 0){
                $h_flag = true;
                return 1;
            }else{
                
                return -21;
            }
        }
        /* each instruction handling*/
        switch(strtoupper($instruction)){
            case 'RETURN': //none
                if(sizeof($line_split) != 1) return -23;
                if(count($line_split) != 1){
                    return -23;
                }else{
                    $i_count++;
                    return 2;
                }
                break;
            case 'BREAK': //none
                if(sizeof($line_split) != 1) return -23;
                $i_count++;
                return 3;
            case 'CREATEFRAME': //none
                if(sizeof($line_split) != 1) return -23;
                $i_count++;
                return 4;
            case 'PUSHFRAME': //none
                if(sizeof($line_split) != 1) return -23;
                    $i_count++;
                    return 5;
            case 'POPFRAME': //none
                if(sizeof($line_split) != 1) return -23;
                    $i_count++;
                    return 6;
            case 'DEFVAR': //<var>
                if(sizeof($line_split) != 2) return -23;
                if(checkVar($line_split[1]) > 0){
                    $i_count++;
                    $arg1 = $line_split[1];
                    return 7;
                }else{
                    return -23;
                }
            case 'INT2CHAR': //<var> <symb>
            case 'STRLEN':
            case 'TYPE':
            case 'NOT': //only 2 operands for not?
            case 'MOVE': //<var> <symb>
                if(sizeof($line_split) != 3) return -23;
                /*variable(1st arg) is correct*/
                if(checkVar($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    if(checkSymb($line_split[2]) > 0){
                        if($fflag!="var") $arg2 = getSymbType($line_split[2]);
                        else $arg2 = $line_split[2];
                        $i_count++;
                        $header = strtoupper($instruction);
                        return 8;
                    }else{
                        return -23;
                    }
                }else{ //incorrect <var>
                    return -23;
                }
            case 'CALL': //<label>
            case 'LABEL':
            case 'JUMP':
                if(sizeof($line_split) != 2) return -23;
                if(checkLabel($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    $header = strtoupper($instruction);
                    $i_count++;
                    return 9;
                }else{
                   return -22;
                }
            case 'WRITE': //<symb>
            case 'DPRINT':
            case 'EXIT':
            case 'PUSHS':
                if(sizeof($line_split) != 2) return -23;
                if(checkSymb($line_split[1]) > 0){
                    if($fflag == 'string' || $fflag == 'bool' || $fflag == 'int' || $fflag == 'nil')
                        $arg1 = substr($line_split[1], strlen($fflag)+1);
                    else $arg1 = $line_split[1];
                    $i_count++;
                    $header = strtoupper($instruction);
                    return 10;
                }else{
                    return -23;
                }
            case 'POPS': //<var>
                if(sizeof($line_split) != 2) return -23;
                if(checkVar($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    $i_count++;
                    return 11;
                }else{
                    return -23;
                }
            case 'ADD': // ⟨var⟩ ⟨symb1⟩ ⟨symb2⟩
            case 'SUB':
            case 'MUL':
            case 'IDIV':
            case 'LT':
            case 'GT':
            case 'EQ':
            case 'AND':
            case 'OR':
            case 'CONCAT':
            case 'GETCHAR':
            case 'SETCHAR':
            case 'STRI2INT': //⟨var⟩ ⟨symb1⟩ ⟨symb2⟩
                if(sizeof($line_split) != 4) return -23;
                if(checkVar($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    if(checkSymb($line_split[2], False) > 0){
                        if($fflag!="var") $arg2 = getSymbType($line_split[2]);
                        else $arg2 = $line_split[2];
                        if(checkSymb($line_split[3], True) > 0){
                            if($aux!="var")$arg3 = getSymbType($line_split[3]);
                            //else $arg3 = $aux;
                            $i_count++;
                            $header = strtoupper($instruction); //store instruction
                            return 12;
                        }else{
                            return -23;
                        }
                    }else{
                        return -23;
                    }
                }else{
                    return -23;
                }
            case 'JUMPIFEQ': //⟨label⟩ ⟨symb1⟩ ⟨symb2⟩
            case 'JUMPIFNEQ':
                if(sizeof($line_split) != 4) return -23;
                if(checkLabel($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    if(checkSymb($line_split[2], False) > 0){
                        if($fflag!="var") $arg2 = getSymbType($line_split[2]);
                        else $arg2 = $line_split[2];
                        if(checkSymb($line_split[3], True) > 0){
                            if($aux!="var")$arg3 = getSymbType($line_split[3]);
                            $i_count++;
                            $header = strtoupper($instruction); //store instruction
                            return 13;
                        }else{
                            return -23;
                        }
                    }else{
                        return -23;
                    }
            }else{
                return -23;
            }
            case 'READ':    //<var> <type>
                if(sizeof($line_split) != 3) return -23;
                if(checkVar($line_split[1]) > 0){
                    $arg1 = $line_split[1];
                    if(checkType($line_split[2]) > 0){
                        $arg2 = $line_split[2];
                        $i_count++;
                        $header = strtoupper($instruction);
                        return 14;
                    }
                }
            default:
                return -23;
                
        }
    }
    /*  *   *   *   *   *   *   *   *
     @funcion parseComments
     puts space before every "#", so parser
     can easily identify comments when splitting
     @return parsed comment line.
    *    *  *   *   *   *   *   *   */
    function parseComments($line){
        if(str_contain($line, '#')){
            $line = substr($line, 0, strpos($line, "#"));
            $line = trim($line);
        }
        return $line;
    }
    /*  *   *   *   *   *   *   *   *
     @funcion checkVar
     check validity of given variable
     @return 1 if correct, -1 otherwise
    *    *  *   *   *   *   *   *   */
    function checkVar($var){
        if(preg_match("/(LF|GF|TF)@[a-zA-Z#_\%!\?&*$][a-zA-Z#&*$0-9]*/", $var)){
            return 1;
        }else{
            return -1;
        }
    }
    /*  *   *   *   *   *   *   *   *
     @funcion checkSymb
     check validity of given symbol, constant(string@asd)
     or variable(LF@_g) are valid. Writes type of given
     symbol to $fflag.
     @var $multiple -> indicates whenever multiple symbols
     within instruction are being processed.
     @return 1 if correct, -1 otherwise
    *    *  *   *   *   *   *   *   */
    function checkSymb($symb, $multiple = False){
        global $fflag, $aux, $arg2,$arg3;
        //constant
        if(preg_match("/(int|bool|string|nil)@[a-zA-Z#_\-&*$]*[a-zA-Z#&*$0-9]*/", $symb)){
            /*need auxillary variable when multiple symbols are present*/
            $split = explode("@", $symb);
            /*each type must match correct notation*/
            switch($split[0]){
                    case 'bool':
                        if(!preg_match("/(true|false)/", $split[1])) exit(badSourceCode);
                        break;
                    case 'int':
                        if(!preg_match("/[0-9]/", $split[1])) exit(badSourceCode);
                        break;
                    case 'string':

                        break;
                    case 'nil':
                        if($split[1] != 'nil') exit(badSourceCode);
                        break;
            }
            
            if($multiple == True){
                $arg3 = getSymbType($symb);
                $aux=getTokenType($symb);
            }else{
                $arg2 = $symb ;
                $fflag = getTokenType($symb);
            }
            return 1;
        }else{
            if(checkVar($symb) > 0){//still can be variable syntax
                if($multiple == True){
                    $arg3 = $symb;
                    $aux = "var";
                }else{
                    $arg2 = $symb;
                    $fflag = "var";
                }
                return 1;
            }
            else{
                return -1;
            }
        }
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion getSymbType
     string@qwe -> qwe
     @return parsed symbol
    *    *  *   *   *   *   *   *   */
    
    function getSymbType($symb){
        $expl = explode("@",$symb);
        $ret = $expl[1];
        return $ret;
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion checkLabel
     check validity of given label
     @return 1 if correct, -1 otherwise
    *    *  *   *   *   *   *   *   */
    function checkLabel($label){
        if(preg_match("/[a-zA-Z#_\-&*$]*[a-zA-Z\#&*$0-9]*/", $label)){
            /*for some reason symbol @ goes throught this regex*/
            if (str_contain($label, '@') || str_contain($label, '/')) {
                exit(badSourceCode);
            }else if (checkEscapeSequence($label) == -1) exit(badSourceCode);
            return 1;
        }else{
            return -1;
        }
    }
    /*  *   *   *   *   *   *   *   *
     @funcion checkType
     check validity of given type
     @return 1 if correct, -1 otherwise
    *    *  *   *   *   *   *   *   */
    function checkType($type){
        if ($type == "int" ||$type == "string" ||$type == "bool"){
            return 1;
        }else{
            return -1;
        }
    }
    /*  *   *   *   *   *   *   *   *
     @funcion getTokenType
     @return type of given token such as:
     string@aaa -> returns 'string' if correct.
     returns -22 if escape sequence is incorrect
    *    *  *   *   *   *   *   *   */
    function getTokenType($token){
        $aux = explode("@",$token);
        //check escape sequence
        if(!empty($aux[1])){
           //check whenever escape is present
           if(str_contain($aux[1], '\\')){
               //check if it's correct
               if(checkEscapeSequence($aux[1]) == -1){
                   exit(badSourceCode);
               }
           }
        }
        return $aux[0];
    }
    /*   *   *   *   *   *   *   *   *
     @function checkEscapeSequence
      check whenever escape sequence is
      correct \xyz where 'xyz' are decadic
      numbers.
     @return 1 if correct, -1 otherwise.
    *    *   *   *   *   *   *   *   */
    function checkEscapeSequence($string){
        $count = 0;
        $contains = false;
        if(str_contain($string, '\\')) $contains = true;
        $arr = array();
        //push all sequences into array
        while(str_contain($string, '\\')){
            $pos = strpos($string, '\\') + 1;
            $string = substr($string, $pos);
            $aux = substr($string, 0, 3);
            array_push($arr,$aux);
        }
        //check each sequence
        while($item = array_shift($arr)){
            $count++;
            if(is_numeric($item)){
                continue;
            }else{
                return -1;
            }
        }
        //empty array;
        if($count == 0 && $contains) return -1;
        //everything ok
        return 1;
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion createNode
    *    *  *   *   *   *   *   *   */
    function createNode($op, $DOM, $program){
        global $i_count;
        $node = $DOM->createElement("instruction", "\n");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        return;
    }

    /*  *   *   *   *   *   *   *   *
     @funcion str_contain
     alternative to str_contains from PHP 8.0
    *    *  *   *   *   *   *   *   */
    function str_contain($str, $symb){
        if(strstr($str, $symb) != false) return true;
        else return false;
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion createAritmeticNode
     creates one of ADD,SUB,MUL,IDIV,LT/LG/EQ, AND/OR/NOT
     node, since they
     have same circumstances
    *    *  *   *   *   *   *   *   */
    function createAritmeticNode3($op, $DOM,$program){
        global $i_count, $arg1, $arg2, $arg3, $aux, $fflag;
        $node = $DOM->createElement("instruction");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        $argnode1 = $DOM->createElement("arg1", $arg1);
        $argnode1->setAttribute("type", "var");
        $node->appendChild($argnode1);
        $argnode2 = $DOM->createElement("arg2", $arg2);
        $argnode2->setAttribute("type", $fflag);
        $node->appendChild($argnode2);
        $argnode3 = $DOM->createElement("arg3", $arg3);
        $argnode3->setAttribute("type", $aux);
        $node->appendChild($argnode3);
        return;
    }
    /*  *   *   *   *   *   *   *   *
     @funcion createVarSymbNode
     creates one of <var> <symb> nodes(MOVE, INT2STRING)
     node, since they have same circumstances
    *    *  *   *   *   *   *   *   */
    function createVarSymbNode($op, $DOM, $program){
        global $i_count, $arg1, $arg2,$fflag;
        $node = $DOM->createElement("instruction");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        $argnode1 = $DOM->createElement("arg1", $arg1);
        $argnode1->setAttribute("type", "var");
        $node->appendChild($argnode1);
        $argnode2 = $DOM->createElement("arg2", $arg2);
        $argnode2->setAttribute("type", $fflag);
        $node->appendChild($argnode2);
        return;
    }
    /*  *   *   *   *   *   *   *   *
     @funcion createSymbNode
     creates one of <symb> nodes(EXIT, WRITE, etc..)
     node, since they have same circumstances
    *    *  *   *   *   *   *   *   */
    function createSymbNode($op, $DOM, $program){
        global $i_count, $arg1, $fflag;
        $node = $DOM->createElement("instruction");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        $argnode1 = $DOM->createElement("arg1", $arg1);
        $argnode1->setAttribute("type", $fflag);
        $node->appendChild($argnode1);
        return;
    }
    /*  *   *   *   *   *   *   *   *
     @funcion createSymbNode
     creates one of <symb> nodes(LABEL, CALL, et..)
     node, since they have same circumstances
    *    *  *   *   *   *   *   *   */
    function createLabelNode($op, $DOM, $program){
        global $i_count, $arg1;
        $node = $DOM->createElement("instruction");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        $argnode1 = $DOM->createElement("arg1", $arg1);
        $argnode1->setAttribute("type", "label");
        $node->appendChild($argnode1);
        return;
    }
    
    /*  *   *   *   *   *   *   *   *
     @funcion createLabelSymb2
     creates one of <label> <symb1> <symb2>
     nodes(LABEL, CALL, et..)
     node, since they have same circumstances
    *    *  *   *   *   *   *   *   */
    function createLabelSymb2($op, $DOM, $program){
        global $i_count, $arg1, $arg2, $arg3, $aux, $fflag;
        $node = $DOM->createElement("instruction");
        $node->setAttribute("order", $i_count);
        $node->setAttribute("opcode", $op);
        $program->appendChild($node);
        $argnode1 = $DOM->createElement("arg1", $arg1);
        $argnode1->setAttribute("type", "label");
        $node->appendChild($argnode1);
        $argnode2 = $DOM->createElement("arg2", $arg2);
        $argnode2->setAttribute("type", $fflag);
        $node->appendChild($argnode2);
        $argnode3 = $DOM->createElement("arg3", $arg3);
        $argnode3->setAttribute("type", $aux);
        $node->appendChild($argnode3);
        return;
    }
    
    /* main */
    argumentValidity($argc);
    syntaxAnalysis();
    return 0;
?>
