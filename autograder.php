<?php

$burl = "https://afsaccess4.njit.edu/~sa2452/cs490_assign2/select/select_exam_data.php";

$data = file_get_contents('php://input');
$ch = curl_init($burl);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);     
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch); 
curl_close($ch);

$examData = json_decode($result, true);

// Outer most loop iterates through the exam questions
if (!empty($examData)) {
    for ($n = 0; $n < count($examData); $n++) {

        $grades["exam_title"] = $examData[$n]["exam_title"];    //passed with every question
        $rvalues["exam_title"] = $examData[$n]["exam_title"];

        // Regex to capture function name for comparison with students'
        $pattern = "/[A-Za-z0-9]+\(/";
        preg_match($pattern, $examData[$n]["question"], $matches);
        $functionName = $matches[0];

        // String searching to check for constraints
        $stringPointDivisor = 2;
        $question = $examData[$n]["question"];
        if (!empty($examData[$n]["constaint"])) {
                    if (strpos($question, "loop") != false) {
                if (strpos($question, "for") != false) {
                    $constraint = "for";
                } else {
                    $constraint = "while";
                }
                $stringPointDivisor = 4;  
            } else if (strpos($question, "recursion") != false) {
                $constraint = $functionName; // function name appears more than once
                $stringPointDivisor = 4;        
            } else {       
                $stringPointDivisor = 2;
                $constraint = "";
            }
        }

        // Outer loop to iterate through the student responses 
        // studentResponses is an array that holds each student answer as an associative array
        for ($i = 0; $i < count($examData[$n]["studentResponses"]); $i++) {

            // Loop to iterate through inner assocative array
            foreach ($examData[$n]["studentResponses"][$i] as $user => $response) {
                
                // Students start with full points
                $question = $examData[$n]["question"];
                $grades[$question][$user]["total"] = $examData[$n]["points"];
                
                // String comparison with function names
                preg_match($pattern, $response, $matches);
                $studentFName = $matches[0];

                if (strcmp($functionName, $studentFName) == 0) {
                    $grades[$question][$user]["functionName"] = $examData[$n]["points"] / $stringPointDivisor;
                    $grades[$question][$user]["functionName"] = round($grades[$question][$user]["functionName"], 2);
                } else {
                    // Corrects function name
                    $response = preg_replace($pattern, $functionName, $response);
                    // if constraint exists, string searching worth 1/4 each
                    $grades[$question][$user]["total"] -= $examData[$n]["points"] / $stringPointDivisor; // worth half
                    $grades[$question][$user]["functionName"] = 0;
                }

                // Constraint check
                if (!empty($examData[$n]["constaint"])) {
                    if (strcmp($constraint, "for") == 0 || strcmp($constraint, "while") == 0) {
                        if (strpos($response, $constraint) != false) {
                            $grades[$question][$user]["constraint"] = $examData[$n]["points"] / $stringPointDivisor;
                            $grades[$question][$user]["constraint"] = round($grades[$question][$user]["constraint"], 2);
                            
                        } else {
                            $grades[$question][$user]["constraint"] = 0;
                            $grades[$question][$user]["total"] -= ($examData[$n]["points"] / $stringPointDivisor);

                        }
                    } else {
                        if (substr_count($response, $constraint) > 1) {
                            $grades[$question][$user]["constraint"] = $examData[$n]["points"] / $stringPointDivisor;
                            $grades[$question][$user]["constraint"] = round($grades[$question][$user]["constraint"], 2);
                            
                        } else {
                            $grades[$question][$user]["constraint"] = 0;
                            $grades[$question][$user]["total"] -= (($examData[$n]["points"] / 2) / $stringPointDivisor);
                            
                        }
                    }
                }
                
                // Writes student response to file to test output
                $file = fopen("testing/tester.py", "w");
                fwrite($file, "#!/usr/bin/env python\n$response");

                //Checks for number of testcases 
                $testcases = array();
                foreach($examData[$n]["testcases"] as $testcase) {
                    if (!empty($testcase)) {
                        array_push($testcases, $testcase);
                    }
                }
                unset($output);         //unset so student outputs are not appended

                // Array to save test cases for grading array
                $tc = array();

                foreach($testcases as $functionCall) {
                    $file = fopen("testing/tester.py", "a");
                    $text = ("\nprint($functionCall)");
                    fwrite($file, $text);
                    fclose($file);
                    array_push($tc, $functionCall);
                }
                
                // unset after each question/student
                try { 
                    exec('python /afs/cad.njit.edu/u/a/g/ag835/public_html/490/delta/testing/tester.py', $output, $returnval);
                    $rvalues[$question][$user] = $output;
                } catch (Exception $e) {
                    echo $e->getMessage();
                }


                // Checks for number of answers
                $answers = array();
                foreach($examData[$n]["outputs"] as $answer) {
                    if (!empty($answer)) {
                        array_push($answers, $answer);
                    }
                }
                $j = 0;
                foreach($answers as $value) {
                    if ($output[$j] == $value) {
                        $grades[$question][$user][$tc[$j]] = (($examData[$n]["points"] / 2) / count($answers));
                        $grades[$question][$user][$tc[$j]] = round($grades[$question][$user][$tc[$j]], 2); # round to 2 decimal places
                        
                    } else {
                        $grades[$question][$user]["total"] -= (($examData[$n]["points"] / 2) / count($answers));
                        $grades[$question][$user][$tc[$j]] = 0;
                    }
                    $j++;
                }
                $grades[$question][$user]["total"] = strval(round($grades[$question][$user]["total"], 2));                
            }
        }
    }
    
    $grades["graded"] = 1;              //flag for back end
    $gradesJSON = json_encode($grades);
    $rvaluesJSON = json_encode($rvalues);

    // Send grades to database
    $curl = "https://afsaccess4.njit.edu/~sa2452/cs490_assign2/insert/insert_grade.php";

    $ch2 = curl_init($curl);

    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, $gradesJSON);  
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

    $result2 = curl_exec($ch2); 
    curl_close($ch2); 

    echo $result2;  
    
    $rurl = "https://afsaccess4.njit.edu/~sa2452/cs490_assign2/insert/insert_code_output.php";

    $ch3 = curl_init($rurl);

    curl_setopt($ch3, CURLOPT_POST, true);
    curl_setopt($ch3, CURLOPT_POSTFIELDS, $rvaluesJSON);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);

    $result3 = curl_exec($ch3); 
    curl_close($ch3); 

    echo $result3;

} else {
    echo "ERROR: NO EXAM DATA\n";
}