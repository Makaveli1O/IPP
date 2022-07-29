# Documentation for 1st script (parse.php) IPP 2020/2021
Name and Subname: Samuel Líška
Login: xliska20

## Functionality(parse.php)
Parser is implemented in functional  paradigm. After checking validity of given arguments, function **syntaxAnalysis()** starts to scan input line by line. Each line is sent to **scanner()**, which starts parsing one line at the time(ignoring comments, converting instruction, checking syntax etc.). At the end scanner returns *retval* back to **syntaxAnalysis()**. Depending on *retval*, syntaxAnalysis starts to build DOM tree using  ***DOMDocument*** object. Errors are handled within **scanner()** function.

## Functions:
**argumentValidity()**: Parse and check validity of arguments.
**syntaxAnalysis()**:Creates DOM tree, and runs syntax analysis for given IPP21 code.
**scanner($line)**:Scans input, and checks its correctness line by line given from *syntaxAnalysis()*. Multiple funcions are being called within *scanner()*
**parseComments($line)**: Handles comments within given IPPcode21.
**getSymbType($symb)**: Returns parsed symbol. *return* type of given symbol.
**checkVar($var)**: Checks validity of given variable. *return* 1 if correct -1 otherwise.
**checkLabel($label)**: Checks validity of given label. *return* 1 if correct -1 otherwise.
**checkType($type)**: Check validity of given type. *return* 1 if correct -1 otherwise.
**checkSymb($symb, $multiple)**: Checks validity of given symbol/symbols. *return* 1 if correct -1 otherwise. Or exit(23) if fatal error is found.
**getTokenType($token)**: Returns type of given token such as: string@aaa -> returns 'string' if correct. 
**checkEscapeSequence*()*: Check whenever escape sequence is correct -> \xyz where 'xyz' are decadic numbers.
**createNode($op, $DOM, $program)**: Creates simple node without any operands.
**createAritmeticNode3($op, $DOM, $program)**: Creates advanced node with 3 operands(mostly used by arithmetic instructions).
**createVarSymbNode($op, $DOM, $program)**: Creates node constructed with <var> and <symb> operands.
**createSymbNode($op, $DOM, $program)**: Creates node with only <symb> operand.
**createLabelNode($op, $DOM, $program)**: Creates node with onle <label> operand.
**createLabelSymb2($op, $DOM, $program)**: Creates special node with 3 operands type of <label> <symb1> <symb2>

## **Test(php)**
***Test*** trieda sa skladá z parametrov: **filename**(meno spracovávajúceho súboru), **parseScript**(obsahuje skript pre spustenie parseru), **intScript**(obsahuje skript pre spustenie interpretu), **parseOnly**(true|false podľa prítomnosti *--parse-only*), **intOnly**(true|false podľa prítomnosti *--int-only*), **jexamScript**(obsahuje skript pre spustenie jexam-u), **msg**(správa  v prípade chyby) a **expectedCode**(očakávaný kód pre daný test), `Test->__construct()` nadefinuje všetky spomínané parametre triedy(napr **intOnly** cez funkciu **intOnly()** ktorá je mimo triedy ***Test***, prípadne **expectedCode** z .rc súboru). `Test->run()` vykoná samotné prevedenie testu. Podľa parametrov *intOnly* a *parseOnly* sa vykoná jedno alebo druhé, v prípade ak sú oba *false* vykoná sa najskôr parsovanie pomocou `Test->executeParse()`, uložené do *tmp* a jeho výsledok sa spracuje interpret skriptom pomocou `Test->executeInt()`. Výsledky(output a rc) sú následne porovnávané nástrojmi *jexamxml* alebo *diff*. ***findTests()*** je funkcia ktoré prejde zadaným adresárom(v prípade *--recursive* využije *RecursiveDirectoryIterator*), a následne spracováva všetky súbory s koncovkou *.src*(súbory musia byť čitateľné inak ich preskočí). Ak *.src* súbor neobsahuje súbory s rovnakým menom ale koncovkami *.in*, *.rc*, *.out*, tak ich vytvorí a naplní podľa zadania. Mená spracovaných súborov sa následne pushujú do **tests** poľa. 
***Funkčnosť:*** Po zavolaní `argumentValidity()` sa do **tests** uloží výstup z `findTests()`(názvy testovacích súborov) pre ktoré sa vytvoria inštancie triedy ***Test*** a vykonajú sa testy. Následne sa na základe výsledkov vygeneruje **html** súbor s výsledkami ktorý sa vypíše na stdout.


## **Interpret(python)**
***Errors*** je trieda obsahujúca chybové stavy a ich kódy. ***Stack*** trieda vykonávajúca úlohu abstraktného dátového typu zásobník(t.j. klasické funkcie `Stack->push` pre pridanie na zásobník, `Stack–>pop` pre odstránenie z vrcholu zásobníka + `Stack->Sprint` pre testovacie účely. ***CallStack*** zásobník pre volania funkcií `CallStack->push`, `CallStack->pop` + `CallStack->Cprint`. ***FrameStack*** ktorá reprezentuje zásobník pre rámce(LF), `FrameStack–>push`, `FrameStack->pop` + `FrameStack->Fprint` a funkcie `FrameStack->top` pre vrátenie rámca na vrchole zásobníka a`FrameStack->isEmpty`(true|false) pre zístenie či je zásobnik prázdny.**eprint()** funkcia vypisuje chyby na *stderr*. **verifyArguments()** skontroluje argumenty a nastaví globálne premenné(*--source* = ***source***,*--input* = ***inpt***).***loadInstructions()*** zoradí inštrukcie podľa *order*, následne vytvorí z uzlov v xml list aby bolo možné vytvoriť "skákací" loop(*while* s counterom kôli **JUMP** inštrukciám) v ktorom sa jednotlivé uzly rozložia skontrolujú a pošlú do ***handleInstruction()*** . Tu sa následne spracovávajú samotné inštrukcie, prebieha kontrola argumentov zadanej inštrukcie a pomocou funkcií ***checkSymb()***, ***checkVar()*** a ***checkLabel()*** , ***checkType()*** kontroluje syntaktická korektnosť argumentov inštrukcie. Pri argumentoch typu *symb* a *var* sa využívajú funkcie ***pushVariable()*** (pushne premennu na zadaný rámec)***getVariable()*** (zo zadaného rámca získa hodnotu a typ premennej) a ***setVariable()*** (redefinuje premennú pushnutú v rámci). Všetky tri funkcie pracujú s rámcami. (napr. pri inštrukcií **MOVE** sa skontroluje pomocou **argCout()** počet argumentov, následne sa skontroluje *<var>* syntax pomocou **checkVar()** z výsledku sa zostí typ, meno a frame premennej. Na drohý argument typu *<symb>* sa zavolá obdobná funkcie **checkSymb()**. Na konci sa pomocou **setVariable()** nastaví hodnota *<var>* na zásobníku na hodnotu a typ *<symb>*), Funkcie ***detectEscapeSequence()*** spracováva escape sekvencie v stringoch pomocou lambda funkcie. Funkcia **createFrame()** vytvorí dočasný frámec TF. **pushFrame()** vytvorený TF pushne na zásobník rámcov *frameStack* (LF). **popFrame** vráti posledný pushnutý rámec.
***Funkčnosť***: V  **main** sa inicializujú globálne premenné pre ukladanie framov, labelov a inštrukcií. Následne sa  zavolá **verifyArguments()** ktorá skontroluje validnosť ardgumentov. Po odpovedi z funkcie sa rozhodne, či sa **content**(obsah xml) začne spracovávať zo súboru, prípadne zo stdin pomocou **ElementTree** knižnice. Následne funkcia **loadInstructions()** zoradí uzly podľa *'ORDER'* atribútu, a vo for cykle všetky *child* elementy vloží do slovníka **instructions**(napr [{1 : Element...}]), ktorý sa vo *while* cykle začne spracovávať(tento krok bol nutný pretože v klasickom *for element in root* nieje možné skákať v prípade jumpov v inštrukciách), zavolá ***handleInstruction()*** ktorá vracia pre každú inštrukciu 0(inštrukcie typu *LABEL* ukladá do slovníka **labels{}**). V prípade skokových funkcií(*JUMP*,*JUMPIFEQ*,*JUMPIFNEQ*,*CALL*,*RETURN*) vráti buď integer pre **order** prípadne meno návěští(napr *string foo*). V prípade forward jumpu na sa zavolá funkcia  **findLabel()** ktorá zistí **order** pre daný label(ak existuje)n a tento label sa následne while cyklus posunie. V prípade že label už bol spracovaný hlavnou smyčkou(nachádza sa v  **labels{}** tzv. backward jump) tak sa rovno mení počítadlo cyklu pre skok.
## Autor
Samuel Líška(**xliska20**)

