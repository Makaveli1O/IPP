#!/usr/bin/env python3
# -*- coding: UTF-8 -*-
#@author: Samuel Liska (xliska20)
import argparse #arguments handling
import sys #basic library
import enum #enum
import xml.etree.ElementTree as etree #xml handling
import re # regex
#   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #   #	#   #	#

# -------- Classes -------#
#MARK: ErrorClass
#simple error class
class Error(enum.Enum):
    arguments = 10
    file = 11
    wellFormed = 31
    xml = 32
    redefine = 52
    wrongType = 53
    nonvar = 54
    noframe = 55
    missingValue = 56
    wrongValue = 57
    string = 58
    internal = 99
    
#simple stack class
#MARK: Stack Class
class Stack:
    def __init__(self):
        self.stack = []
    
    def push(self, _type = None, value = None):
        self.stack.append((_type, value))
        
    #debug func
    def Sprint(self):
        for i in self.stack:
            print(i)
        
    def pop(self):
        try:
            return self.stack.pop()
        except:
            eprint("ERROR: Missing Value.")
            exit(Error.missingValue.value)
#simple stack for calls
#MARK: CallStack Class
class CallStack:
    def __init__(self):
        self.stack = []
    
    def push(self, order):
        self.stack.append(order)
        
    #debug func
    def Cprint(self):
        for i in self.stack:
            print(i)
    def pop(self):
        try:
            return self.stack.pop()
        except:
            eprint("ERROR: Missing Value.")
            exit(Error.missingValue.value)

#simple stack for LF
#MARK: FrameStack Class
class FrameStack:
    def __init__(self):
        self.stack = []
    
    def push(self, frame):
        self.stack.append(frame)
        
    def isEmpty(self):
        if not self.stack:
            return True
        else:
            return False
    #debug func
    def Fprint(self):
        for i in self.stack:
            eprint(i)

    def top(self):
        try:
            return self.stack[-1]
        except:
            eprint("ERROR: Frame does not exist.")
            exit(Error.noframe.value)
             
    def pop(self):
        try:
            return self.stack.pop()
        except:
            eprint("ERROR: Frame does not exist.")
            exit(Error.noframe.value)

stack = Stack()
callStack = CallStack()
frameStack = FrameStack()# LF
# -------- end of Classes --------#
   
#print alternative (stderr)
#MARK: eprint
def eprint(*args, **kwargs):
    print(*args, file=sys.stderr, **kwargs)

"""
@function verifyArguments:
Verifies given arguments. and set global variables for further usage.
Return False if at least one argument was used. True otherwise
"""
#MARK: verifyArguments
def verifyArguments():
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', dest="sourceFile", required=False, help="Source file with XML representaition of source code according to definition.")
    parser.add_argument('--input', dest="inputFile", required=False, help="File containing inputs for interpretation of given code.")
    args = parser.parse_args()
    args_vars = vars(args)

    global source
    global inpt
    source = None if not args.sourceFile else args.sourceFile
    inpt = None if not args.inputFile else args.inputFile

    if source is None and inpt is None:
        eprint("ERROR: no input and source files.")
        exit(Error.arguments.value)
        
    if source is None:
    	return "source"
    elif inpt is None:
        return "inpt"
    else:#both are set
        return True
     
"""
@function loadInstructions:
Loads instruction within given XML tree.
"""
#MARK: loadInstructions
def loadInstructions(xml):
    instruction = ""
    #order XML by 'order'
    xml[:] = sorted(xml, key=lambda child: (child.tag,child.get('order')))
    orders = []
    #count = 0
    #need loop with push to jumpable dictionary
    for child in xml:
        order = child.get('order')
        
        try:
            # check if order is int
            order = int(order)
        except:
            eprint("ERROR: unable to get order")
            sys.exit(Error.xml.value)

        # push instructions to dictionary
        if child.tag == 'instruction' and order > 0 and order not in instructions:
            instructions[order] = child
            orders.append(order)
        else:
            eprint("ERROR: Wrong xml val")
            sys.exit(Error.xml.value)
    i = 1
    jump = 0 #store jump on specific order
    returning = False #flag indicates that returning to call is happening
    returned = False #flag indicates that previous instruction returned to this one
    #jumpable loop was required
    while i <= max(orders):
        #problematic jump instructions(on the very end
        if returned:
            if(i == max(orders)):
                i = i+1
                break
            jump = 0
            i = i+1
            continue
        if i in sorted(orders):
            #jumps(returned from instruction handler)
 
            if jump != 0:
                if returning:
                    i = int(jump)
                    jump = 0
                    returning = False
                    returned = True
                elif jump is None:
                    jump = 0
                #forward jump(returned name of label)
                elif isinstance(jump,str):
                    i = findLabel(jump,xml)
                    jump = 0
                #backward jump(returned number)
                else:
                    if jump not in orders:
                        eprint("ERROR: Jumping on non existing label")
                        exit(Error.redefine.value)
                    i = int(jump)
                    jump = 0
                    
            
            child = instructions[i]
            #jump was called on order in 'jump' variable
            try:
                order = int(child.attrib['order'])
                instruction = child.attrib['opcode']
            except:
                eprint("ERROR: Op code or order is missing. ")
                exit(Error.xml.value)
            if int(child.attrib['order']) <=0: #or previousOrder >= int(child.attrib['order']):
                eprint("ERROR: Wrong instruction order.")
                exit(Error.xml.value)
            if child.tag != "instruction":
                eprint("ERROR: Wrong element tag.")
                exit(Error.xml.value)
            jump = handleInstruction(child, instruction)
            if instruction == 'RETURN' and jump !=0:
                returning = True
                i = int(jump)
            elif jump is None or jump == 0:
                i = i+1
            else:
                continue
        else:#some numbers might not
            i = i+1
    #all instruction were handled, and no label for jump was found.
    if jump!=0:
        #one last chance to find label if last instruction is jumpable
        eprint("ERROR: Jumping on non existing label")
        exit(Error.redefine.value)
    return
"""
@function findLabel
finds order of given label for jump
"""
def findLabel(name,xml):
    labels=xml.findall(".//*[@opcode='LABEL']")
    for label in labels:
        for child in label:
            if child.text == name:
                return int(label.attrib['order'])
    #else not found
    eprint("ERROR: Jumping label does not exist in future.")
    exit(Error.xml.value)
                
    
"""
@function handleInstruction:
Handles instruction and do correspoinding operations
according to opcode. Main instruction handler
"""
#MARK: handleInstruction
def handleInstruction(element, instruction):
    #all arguments elements check
    checkArgs(element)
    
    instruction = instruction.upper()
    #Frames, function calls
    # - -- - - - - - - - - - -
    if instruction == 'MOVE': #⟨var⟩ ⟨symb⟩
        #check arg count
        argCount(element, 2)
        _type,arg = checkVar(element, [0])
        #results are in list coz of instruction with multiple arguments
        _type = _type[0]
        arg = arg[0]
        
        frame, name = arg.split('@')
        
        sType,sArg = checkSymb(element,[1])
        sType = sType[0]
        sName = sArg[0]
        setVariable(frame, name, sType, sName)
        
        return
    #MARK: frames
    elif instruction == 'CREATEFRAME':
        argCount(element, 0)
        createFrame()
        return 0
    elif instruction == 'PUSHFRAME':
        argCount(element, 0)
        pushFrame()
        return 0
    elif instruction == 'POPFRAME':
        argCount(element, 0)
        popFrame()
        return 0
    elif instruction == 'DEFVAR': # ⟨var⟩
        argCount(element, 1)
        _type, arg = checkVar(element, [0])
        _type = _type[0]
        arg = arg[0]
        frame, name = arg.split("@")
        pushVariable(frame,name)
    elif instruction == 'CALL': # ⟨label⟩
        argCount(element, 1)
        _type, arg, order = checkLabel(element, [0])
        _type = _type[0]
        arg = arg[0]
        callStack.push(order)
        return arg
    elif instruction == 'RETURN':
        argCount(element, 0)
        return callStack.pop()
    #MARK: stack
    # Stack operations
    # - -- - - - - - - - - - -
    elif instruction == 'PUSHS': # ⟨symb⟩
        argCount(element, 1)
        argType, argName = checkSymb(element, [0])
        stack.push(argType, argName)
        return 0
    elif instruction == 'POPS': # ⟨var⟩
        argCount(element, 1)
        #check if var is ok first
        _type, name = checkVar(element, [0]) #name -> GF@a
        _type = _type[0]
        name = name[0]
        
        frame, name = name.split('@')
        #get values from top of the stack
        sType, sName = stack.pop()
        sType = sType[0]
        sName = sName[0]
        #now assign stack values to checked variable
        
        setVariable(frame,name,sType,sName)
        return 0
    #MARK: arithmetic
    # Aritmetic operations
    # - -- - - - - - - - - - -
    #⟨var⟩ ⟨symb1⟩ ⟨symb2⟩
    elif instruction == 'SUB' or instruction == 'MUL' or instruction == 'IDIV' or instruction == 'ADD':
        argCount(element, 3)
        type1,name1 = checkVar(element,[0])
        type1 = type1[0]
        name1 = name1[0]
        f1,n1 = name1.split("@")#store for later
        #variable is defined
        answer = getVariable(f1,n1)
        if isinstance(answer, tuple):
            type1,name1 = answer
        else:
            type1, name1 = (answer, 0)

        
        type2,name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        type3,name3 = checkSymb(element,[2])
        type3 = type3[0]
        name3 = name3[0]
        if type2 == 'var':
            frame, name = name2.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type2,name2 = answer
            else:
                eprint("ERROR: Variable is not defined.")
                exit(Error.missingValue.value)
            #type2,name2 = answer
        elif type3 == 'var':
            frame, name = name3.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type3,name3 = answer
            else:
                eprint("ERROR: Variable is not defined.")
                exit(Error.missingValue.value)
            #type3,name3 = answer
        name2 = representsInt(name2)
        name3 = representsInt(name3)

        if type2 != 'int' or type3 != 'int':
            eprint("ERROR: Incorrect type.")
            exit(Error.wrongType.value)
        checkMathTypes(type2,type3) #symbols can be var/int/string/bool/nil
        if instruction == 'ADD':
            name1 = name2+name3
        elif instruction == 'SUB':
            name1 = name2-name3
        elif instruction == 'MUL':
            name1 = name2*name3
        elif instruction == 'IDIV':
            if name3 == 0:
                eprint("ERROR: Division by zero.")
                exit(Error.wrongValue.value)
            name1 = name2/name3
        else:
            eprint("ERROR: Wrong operand")
            exit(Error.wrongValue)
        setVariable(f1, n1, 'int', int(name1))
        return 0
    #MARK: Write/read
    elif instruction == 'WRITE':
        argCount(element, 1)
        _type, name = checkSymb(element, [0])
        _type = _type[0]
        name = name[0]
        if _type == 'var':
            frame, vname = name.split('@')
            answer = getVariable(frame,vname)
            vType, vVal = answer
        else:
            vVal = name
        
        #escape sequences handling
        #wont be changed if does not contain escape sequence
        if isinstance(vVal,int):
            print(vVal, end = '')
            return 0
        elif isinstance(vVal, list):
            print(vVal)
            vVal = vVal[0]
        escape = re.sub(r"\\\d{3}", "", vVal)
        if escape == vVal:
            #print(vVal, end = '')
            for c in vVal:
                if c == '\n':
                    continue
                print(c, end='')
        else:
            escape = detectEscapeSequence(vVal)
            for c in escape:
                if c == '\n':
                    continue
                print(c, end='')
        return 0
        #MARK: read
    elif instruction == 'READ': # ⟨var⟩ ⟨type⟩
        argCount(element, 2)
        global inpt
        global lines
        type1, name1 = checkVar(element, [0])
        type1 = type1[0]
        name1 = name1[0]
        frame,name = name1.split('@')
        type2,name2 = checkType(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        
        if inpt is not None and not lines:
            try:
                f = open(source, "r")
                content = f.read()
                file = open(inpt, "r")
                lines = file.readlines()
                f.close()
            except:
                eprint("ERROR: File error.")
                exit(Error.file.value)
        elif inpt is None:
            lines.append(input())
        setVariable(frame, name, name2, lines[0])
        for line in lines:
            lines.pop(0)
            break
        return 0
    #MARK: jumps
    # jump operations
    # - -- - - - - - - - - - -
    elif instruction == 'LABEL': #<label>
        argCount(element, 1)
        _type, name, order = checkLabel(element, [0])
        _type = _type[0]
        name = name[0]
        if not name in labels:
            labels[name] = order
        else:
            if labels[name] == order:
                #jump caused this, not redefinition
                return 0
            eprint("ERROR: Label already exist.")
            exit(Error.redefine.value)
        return 0
    elif instruction == 'JUMP': #<label>
        argCount(element, 1)
        _type, name, order = checkLabel(element, [0])
        _type = _type[0]
        name = name[0]
        #backJump, return order of instruction
        if name in labels:
            return int(labels[name])
        else:#forward jump return name
            return name
        #MARK: jumpifeq/neq
    elif instruction == 'JUMPIFEQ' or instruction == 'JUMPIFNEQ': #⟨label⟩ ⟨symb1⟩ ⟨symb2⟩
        argCount(element, 3)
        #label
        type1, name1, order = checkLabel(element, [0])
        type1 = type1[0]
        name1 = name1[0]
        type2,name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        type3,name3 = checkSymb(element,[2])
        type3 = type3[0]
        name3 = name3[0]
        if type2 == 'var':
            frame, name = name2.split('@')
            answer = getVariable(frame,name)
            type2,name2 = answer
        elif type3 == 'var':
            frame, name = name3.split('@')
            answer = getVariable(frame,name)
            type3,name3 = answer
            
        if instruction == 'JUMPIFEQ':
            answer = handleJI(type2,name2,type3,name3, "JIEQ")
        else:
            answer = handleJI(type2,name2,type3,name3, "JINEQ")
            
        if answer:
            if name1 in labels:
                return labels[name1]
            else:#forward jump return name
                return name1
        else:
            return
    #MARK: exit/return etc.
    elif instruction == 'EXIT': #<symb>
        argCount(element, 1)
        _type, name = checkSymb(element, [0])
        _type = _type[0]
        name = name[0]
        if _type == 'var':
            frame, name = name.split('@')
            answer = getVariable(frame,name)
            _type, name = answer
            
        if _type != 'int':
            eprint("ERROR: Wrong returning type.")
            exit(Error.wrongType.value)
        exit(int(name))
    elif instruction == 'RETURN':#jump to call
        argCount(element, 0)
        return callStack.pop()
    #MARK: int2char/strlen
    elif instruction == 'INT2CHAR' or instruction == 'STRLEN': # ⟨var⟩ ⟨symb⟩
        argCount(element, 2)
        argCount(element, 2)
        type1, name1 = checkVar(element, [0])
        type1 = type1[0]
        name1 = name1[0]
        frame1,name1 = name1.split('@')
        #answer = getVariable(frame1,name1)
        type2, name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        
        if type2 == 'var':
            frame2, name2 = name2.split('@')
            answer = getVariable(frame2,name2) #response
            if isinstance(answer, tuple):
                type2,name2 = answer
            else:
                eprint("ERROR: Undefined")
                exit(Error.missingValue.value)
                
        if instruction == 'STRLEN':
            if type2 != 'string':
                eprint("ERROR: Wrong type.")
                exit(Error.wrongType.value)
            setVariable(frame1,name1,'int',len(name2))
            return 0
            
        if type2 != 'int':
            eprint("ERROR: Wrong type.")
            exit(Error.wrongType.value)
        try:
            value = chr(name2)
            setVariable(frame1, name1, 'string', value)
        except:
            eprint("ERROR: String error.")
            exit(Error.string.value)
        return 0
    #MARK: dprint
    elif instruction == 'DPRINT': #<symb>
        argCount(element, 1)
        type2, name2 = checkSymb(element,[0])
        type2 = type2[0]
        name2 = name2[0]
        if type2 == 'var':
            frame2, name2 = name2.split('@')
            type2, name2 = getVariable(frame2,name2) #response
        eprint(name2)
        return 0
    #MARK: type
    elif instruction == 'TYPE':
        argCount(element, 2)
        type1, name1 = checkVar(element, [0])
        type1 = type1[0]
        name1 = name1[0]
        frame1,name1 = name1.split('@')
        type2, name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        if type2 == 'var':
            frame2, name2 = name2.split('@')
            try:
                type2, name2 = getVariable(frame2,name2)
            except:
                type2 = ""
        
        setVariable(frame1, name1, "string", type2)
        return 0
    #MARK: LT,GT,EQ
    elif instruction == 'NOT':
        argCount(element, 2)
        type1,name1 = checkVar(element,[0])
        type1 = type1[0]
        name1 = name1[0]
        f1,n1 = name1.split("@")#store for later
        #variable is defined
        answer = getVariable(f1,n1)
        if isinstance(answer, tuple):
            type1,name1 = answer
        else:
            type1, name1 = (answer, 0)
        type2,name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        if type2 == 'var':
            frame, name = name2.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type2,name2 = answer
            else:
                eprint("ERROR: Undefined")
                exit(Error.missingValue.value)
        if type2 != 'bool':
            eprint("ERROR: Wrong type")
            exit(Error.wrongType.value)
        name1 = not name2
        setVariable(f1, n1, "bool", str(name1))
        return 0
    elif instruction == 'LT' or instruction == 'GT' or instruction == 'EQ' or instruction == 'AND' or instruction == 'OR': #<var><symb1><symb2>
        argCount(element, 3)
        type1,name1 = checkVar(element,[0])
        type1 = type1[0]
        name1 = name1[0]
        f1,n1 = name1.split("@")#store for later
        #variable is defined
        answer = getVariable(f1,n1)
        if isinstance(answer, tuple):
            type1,name1 = answer
        else:
            type1, name1 = (answer, 0)
        type2,name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        type3,name3 = checkSymb(element,[2])
        type3 = type3[0]
        name3 = name3[0]
        if type2 == 'var':
            frame, name = name2.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type2,name2 = answer
            else:
                eprint("ERROR: Undefined")
                exit(Error.missingValue.value)
        elif type3 == 'var':
            frame, name = name3.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type3,name3 = answer
            else:
                eprint("ERROR: Undefined")
                exit(Error.missingValue.value)
        if instruction == 'LT' or instruction =='GT' or instruction == 'EQ':
            if type2 != type3:
                eprint("ERROR: Wrong types.")
                exit(Error.wrongType.value)
            if instruction == 'LT':
                name1 = name2 < name3
            elif instruction == 'GT':
                name1 = name2 > name3
            elif instruction == 'EQ':
                name1 = name2 == name3
            setVariable(f1, n1, "bool", str(name1))
            return 0
        if type2 != 'bool' or type3!='bool':
            eprint("ERROR: Wrong types.")
            exit(Error.wrongType.value)
        if instruction == 'AND':
            name1 = name2 and name3
        elif instruction == 'OR':
            name1 = name2 or name3

        setVariable(f1, n1, "bool", str(name1))
        return 0
    #MARK: concat
    elif instruction == 'CONCAT' or instruction == 'GETCHAR' or instruction == 'SETCHAR' or instruction=='STRI2INT': #var symb symb
        argCount(element, 3)
        type1, name1 = checkVar(element, [0])
        type1 = type1[0]
        name1 = name1[0]
        f1,n1 = name1.split('@')
        answer1 = getVariable(f1,n1)
        type2,name2 = checkSymb(element,[1])
        type2 = type2[0]
        name2 = name2[0]
        type3,name3 = checkSymb(element,[2])
        type3 = type3[0]
        name3 = name3[0]
        if type2 == 'var':
            frame, name = name2.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type2,name2 = answer
            else:
                eprint("ERROR: Variable is not defined.")
                exit(Error.missingValue.value)
        elif type3 == 'var':
            frame, name = name3.split('@')
            answer = getVariable(frame,name)
            if isinstance(answer, tuple):
                type3,name3 = answer
            else:
                eprint("ERROR: Variable is not defined.")
                exit(Error.missingValue.value)
        if instruction == 'CONCAT':
            if type2 != 'string' or type3!='string':
                eprint("ERROR: Wrong types.")
                exit(Error.wrongType.value)
            setVariable(f1,n1, 'string', name2+name3)
        elif instruction == 'STRI2INT':
            if type2 != 'string' or type3 != 'int':
                eprint("ERROR: Wrong types.")
                exit(Error.wrongType.value)
            try:
                value = ord(name2[name3])
                setVariable(f1, n1, 'int', value)
            except:
                eprint("ERROR: String error.")
                exit(Error.string.value)
        elif instruction == 'GETCHAR':
            if type2 != 'string' or type3 != 'int':
                eprint("ERROR: Wrong types.")
                exit(Error.wrongType.value)
            name3 = int(name3)
            try:
                setVariable(f1,n1, 'string', name2[name3])
            except:
                eprint("ERROR: Out of range.")
                exit(Error.string.value)
        elif instruction == 'SETCHAR':
            if type2 != 'string' or type3 != 'int':
                eprint("ERROR: Wrong types.")
                exit(Error.wrongType.value)
            name3 = int(name3)
            
            try:
                # convert string to list to change only certain character
                varList = list(answer1)
                varList[name2] = name3[0]
                # convert modified list back to string
                answer1 = ''.join(varList)
                setVariable(f1, n1, 'string', answer1)
            except:
                eprint("ERROR: String Error")
                exit(Error.string.value)
        return 0
    else: #unknow op code
        eprint("ERROR: Unknown Op code:, ",instruction)
        exit(Error.xml.value)
        return 0
    return
"""
@function argCount
counts number of recieved arguments, and compare it with desired
"""
#MARK: argCount
def argCount(element, count):
    i = 0
    for child in element:
        i = i+1
    if i != count:
        eprint("ERROR: Wrong argument count.", element.attrib['opcode'])
        exit(Error.xml.value)
    else:
        return
"""
@function check if types are ok for JI instruction, and do comparison
"""
#MARK: handleJIEQ
def handleJI(type2,name2,type3,name3, JI):
    if type2 == 'int' and type3 =='int':
        name2 = int(name2)
        name3 = int(name3)

    if type2 != type3:
        eprint("ERROR: Different relation types.")
        exit(Error.wrongType.value)
    if JI == 'JIEQ':
        if name2 == name3:
            return True
        else:
            return False
    elif JE == 'JINEQ':
        if name2 != name3:
            return True
        else:
            return False

"""
@function representsInt
check if given variable contains int, and return its value
"""
#MARK: representsInt
def representsInt(s):
    try:
        int(s)
        return int(s)
    except ValueError:
        eprint("ERROR: Arithmetic operation does not contain int")
        exit(Error.wrongType.value)

"""
@function detectEscapeSequence
check if given variable contains int, and return its value
"""
#MARK: detectEscapeSequence
def detectEscapeSequence(string):
    escape = re.sub(r"\\\d{3}", lambda x: esc(x[0]), string)
    return escape
        #aux = substr($string, 0, 3);
        #arr.append(aux)
        
def esc(expression):
    expression = re.findall(r'\d+', expression)
    expression = expression[0]

    return chr(int(expression))
    #return chr(expression[1:])
"""
@function checkArgs
checks element arguments
"""
#MARK: checkArgs
def checkArgs(element):
    args = ['arg1','arg2','arg3']
    for child in element:
        correct = re.search(r'^arg[0-9]+$', child.tag)
        if correct is None:
            eprint("ERROR: Bad argument element.")
            exit(Error.xml.value)
        if child.tag in args:
            args.remove(child.tag)
    if len(args) == 2:
        if 'arg1' in args:
            eprint("ERROR: Bad argument element.")
            exit(Error.xml.value)
    elif len(args) == 1:
        if 'arg1' in args or 'arg2' in args:
            eprint("ERROR: Bad argument element.")
            exit(Error.xml.value)
    return
"""
@function pushVariable:
Pushes variable, according to given frame
"""
#MARK: pushVariable
def pushVariable(frame, name):
    if frame == 'TF':
        #temporary frame must exist first
        if TF is None:
            eprint("ERROR: Temporary frame does not exist.")
            exit(Error.noframe.value)
        else:
            #redefination
            if name in TF:
                eprint("ERROR: Variable has been redefined.")
                exit(Error.redefine.value)
            else:
                TF[name] = 'var'
    if frame == 'GF':
        #variable cannot exist, otherwise throw an error
        if name not in GF:
            GF[name] = 'var'
        else:
            eprint("ERROR: Variable has been redefined.")
            exit(Error.redefine.value)
    if frame == 'LF':
        frame = frameStack.top()
        if name not in  frame:
            frame[name] = (frame, name)
        else:
            eprint("ERROR: Redefined val.")
            exit(Error.redefine.value)
    return
"""
@function redefineVariable:
Redefines given variable within frame.
"""
#MARK: setVariable
def setVariable(frame, name, varType, varValue):
    if frame == 'GF':
        actualFrame = GF
    elif frame == 'TF':
        if TF is not None:
            actualFrame = TF
        else:
            eprint("ERROR: Frame does not exist.")
            exit(Error.noframe.value)
    elif frame == 'LF':
        actualFrame = frameStack.top()
    #name must exist within chosen frame
    if name in actualFrame:
        if varType == 'int':
            actualFrame[name] = (varType, int(varValue))
        else:
            actualFrame[name] = (varType, varValue)
    else:
        eprint("ERROR: Variable does not exist.")
        exit(Error.nonvar.value)
    return
"""
@function getVariable:
recieve given variable from given frame.
"""
#MARK: getVariable
def getVariable(frame,name):
    """ get variable from one of globally defined frames """
    if frame == 'GF':
        actualFrame = GF
    elif frame == 'TF':
        # if frame exists and name is there return it
        if TF is not None:
            actualFrame = TF
        else:
            eprint("ERROR: Frame does not exist.")
            exit(Error.noframe.value)
    elif frame == 'LF':
        actualFrame = frameStack.top()

    try:
        return actualFrame[name]
    except:
        eprint("ERROR: Undefined variable")
        exit(Error.nonvar.value)
        #return ('', '')

    
"""
@function getArgNum
check whenever arg is correct and return its position
"""
#MARK: getArgNum
def getArgNum(arg):
    correct = re.search(r'^arg[0-9]+$', arg)
    if correct is None:
        eprint("ERROR: Bad argument element.")
        exit(Error.xml.value)
    list = arg.split("arg")
    return int(list[1])-1
"""
#function checkType
"""
#MARK: checkType
def checkType(element, argNum):
    typeAnswer = []
    textAnswer = []
    for arg in element:
        count = getArgNum(arg.tag)
        if count in argNum:
            correct = re.search(r'^(int|bool|string)$', arg.text)
            try:
                argType = arg.attrib['type']
            except:
                eprint("ERROR: Arg _type is missing.")
                exit(Error.xml.value)
            if correct is None or argType != 'type':
                eprint("ERROR: Wrong syntax.")
                exit(Error.xml.value)
            else:
                typeAnswer.append(argType)
                textAnswer.append(arg.text)
    return typeAnswer, textAnswer
"""
#function checkVar
Check whenever argument are correct according to variable.
argNum -> if element has multiple arguments, which to process
argNum -> wich arguments to check with checkVar
@return _type and content (as two separated lists)
"""
#MARK: checkVar
def checkVar(element, argNum):
    typeAnswer = []
    textAnswer = []
    for arg in element:
        count = getArgNum(arg.tag)
        if count in argNum:
            correct = re.search(r'^(LF|GF|TF)@[a-zA-Z#_\%!\?&*$][a-zA-Z#&*$0-9]*', arg.text)
            try:
                argType = arg.attrib['type']
            except:
                eprint("ERROR: Arg _type is missing.")
                exit(Error.xml.value)
            if correct is None or argType != 'var':
                eprint("ERROR: Wrong syntax.")
                exit(Error.xml.value)
            else:
                typeAnswer.append(argType)
                textAnswer.append(arg.text)
    return typeAnswer, textAnswer
"""
#function checkSymb
Check whenever argument are correct according to symbol.
constant(int@10) or var(GF@a) syntax is allowed
symb -> constant or var
"""
#MARK: checkSymb
def checkSymb(element, argNum):
    typeAnswer = []
    textAnswer = []
    for arg in element:
        count = getArgNum(arg.tag)
        if count in argNum:
            try:
                argType = arg.attrib['type']
            except:
                eprint("ERROR: Arg _type is missing.")
                exit(Error.xml.value)
            correct = re.search(r'^[a-zA-Z#_\-&*$]*[a-zA-Z#&*$0-9]*', arg.text)
            correctType = re.search(r'^(int|bool|string|nil)$', argType)
            #success symb found
            if correct is not None and correctType is not None:
                typeAnswer.append(argType)
                textAnswer.append(arg.text)
            #check for var syntax now (checkVar will handle errors for var)
            else:
                typeAnswer, textAnswer = checkVar(element,[count])
    return  typeAnswer, textAnswer
      
"""
@function checkLabel
"""
#MARK: checkLabel
def checkLabel(element, argNum):
    typeAnswer = []
    textAnswer = []

    order = element.attrib['order']
    for arg in element:
        count = getArgNum(arg.tag)
        if count in argNum:
            correct = re.search(r'^[a-zA-Z#_\-&*$]*[a-zA-Z\#&*$0-9]*', arg.text)
            try:
                argType = arg.attrib['type']
            except:
                eprint("ERROR: Arg _type is missing.")
                exit(Error.xml.value)
            if correct is None or argType != 'label':
                eprint("ERROR: Wrong syntax.")
                exit(Error.xml.value)
            else:
                typeAnswer.append(argType)
                textAnswer.append(arg.text)
    return typeAnswer, textAnswer, order
    
"""
@function mathOperation
"""
#MARK: checkMathTypes
def checkMathTypes(symb1, symb2):
    int1 = False
    int2 = False
    if symb1 == 'int':
        int1 = True
    if symb2 == 'int':
        int2 = True
    
    
    if int1 and int2:
        return
    else:
        eprint("ERROR: Wrong math symbol types.")
        exit(Error.wrongType.value)
# - -- - - -- - - - -- - - -- - - -
#MARK: FRAMES OPERATIONS
# - - - - - - - -- - - --  -- - - -
"""
@function createFrame
Initializes TF from None to empty frame
"""
#MARK: createFrame
def createFrame():
    global TF
    TF = {}
    return
    
"""
@function pushFrame
Pushes frame on frameStack and set TF to None again
"""
#MARK: pushFrame
def pushFrame():
    global TF
    if TF is not None:
        frameStack.push(TF)
        TF = None
    else:
        eprint("ERROR: Trying to push non existing frame.")
        exit(Error.noframe.value)

"""
@function popFrame
Pops frame from frameStack to TF if none frame is pushed, send an error
Error is handled within frameStack class.
"""
#MARK: popFrame
def popFrame():
    global TF
    TF = frameStack.pop()
    
#MARK: Main
if __name__ == "__main__":
    global GF
    global TF
    global labels # dictionary -> {name: order}
    global instructions
    global lines
    lines = []
    instructions = {}
    labels ={} #dictionary
    GF = {} #global frame dictionary
    TF = None #createfram
    #verify arguments
    answer = verifyArguments() #True for both inpt and source are set
    #read input(file or stdin)
    if answer is True:#both are set input and source
        try:
            f = open(source, "r")
            content = f.read()
        except:
            eprint("ERROR: File error.")
            exit(Error.file.value)
    elif answer == 'inpt':
        try:
            f = open(source, "r")
            content = f.read()
        except:
            eprint("ERROR: File error.")
            exit(Error.file.value)
    elif answer == 'source':
        for line in sys.stdin:
            content += line
        soure = content
    else:
        eprint("ERROR: Error.")
        exit()
    """else:
        content = ""
        for line in sys.stdin:
            content += line
        if answer == 'inpt':
            inpt = content
        if answer == 'source':
            source = content
    """
    #form XML tree
    try:
        xml = etree.fromstring(content)
    except:
        eprint("ERROR: XML is not well formed.")
        exit(Error.wellFormed.value)
    if xml.tag != 'program':
        eprint("ERROR: Wrong root element.")
        exit(Error.xml.value)
    #load instructions
    
    loadInstructions(xml)
