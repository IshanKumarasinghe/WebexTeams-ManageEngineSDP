<?php
$method = $_SERVER['REQUEST_METHOD'];

if ($method== "POST"){
	$requestBody = file_get_contents('php://input');
	$json = json_decode($requestBody);
	$text = $json->queryResult->fulfillmentText;
	$intent = $json->queryResult->intent->displayName;

	//Get original webex webhook content from Dialogflow webhook request
	$webexWebhookRequest =  $json->originalDetectIntentRequest->payload->data;

	$personEmail = strtolower($webexWebhookRequest->data->personEmail);

	
	//check the dialogflow intent
	switch ($intent){
		case 'show-ticket':
			//$speech = $personEmail;

			//connect with the localhost database
			$username = "root";
			$database = "userdata";
			$password = "";
			$mysqli = new mysqli("localhost",$username,$password,$database);
			$Email = strtolower($personEmail);
			$query = "SELECT * FROM userdatatable";
			if ($result = $mysqli->query($query)) {
				//create arrays o email and technicianKey
				$emailArray = array();
				$tokenArray = array();
				$nameArray = array();
				while ($row = $result->fetch_assoc()) {
					$field1name = $row["ID"];
					$field2name = strtolower($row["userEmail"]);
					$field3name = $row["userName"]; //this name should be same as technician name
					$field4name = $row["technicianKey"];
			
					array_push($emailArray,$field2name);
					array_push($tokenArray,$field4name);
					array_push($nameArray,$field3name);
					
				}
				//check the user validity by checking the email
				if (in_array($Email,$emailArray,)){
					//get the user technician Key
					$indexEmail = array_search($Email,$emailArray);
					$tech_key = $tokenArray[$indexEmail];
					$user_name1 = $nameArray[$indexEmail];
					
					//get the tickets by ManageEngine SDP


					$user_name = strtolower($user_name1);
					$curl = curl_init();

					curl_setopt_array($curl, array(
					CURLOPT_URL => "http://localhost:8080/api/v3/requests",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "GET",
					CURLOPT_POSTFIELDS =>"{\n\t\"INPUT_DATA\":{\n    \t\"list_info\": {\n        \t\"row_count\": 20,\n        \t\"start_index\": 1,\n        \t\"sort_field\": \"subject\",\n        \t\"sort_order\": \"asc\"\n    \t}\n\t}\n}",
					CURLOPT_HTTPHEADER => array(
						"Content-Type: application/json",
						"TECHNICIAN_KEY: $tech_key"
					),
					));

					$MEresponse = curl_exec($curl);
					curl_close($curl);
					$MEJsonResponse = json_decode($MEresponse);
					$requests = ($MEJsonResponse->requests);

					$idArray = array();
					$subjectArray = array();
					$descriptionArray = array();
					$requesterArray = array();
					$createdTimeArray = array();

					$req_details = "";

					foreach($requests as $req){
						//check whether the request has an assigned technician
						if($req->technician!=null){
							$technician_name = strtolower($req->technician->name);
							//check whether the request is assigned for the user who is sending the message
							if($technician_name==$user_name){
								array_push($idArray,$req->id);
								array_push($subjectArray,$req->subject);
								array_push($descriptionArray,$req->short_description);
								array_push($requesterArray,$req->requester->name);
								array_push($createdTimeArray,$req->created_time->display_value);
								
								$req_details = $req_details."\n"."ID : ".$req->id."\n"." SUBJECT : ".$req->subject."\n"." REQUESTER : "
												.$req->requester->name."\n"." DESCRIPTION : ".$req->short_description."\n"
												." CREATED_TIME : ".$req->created_time->display_value."\n";

							}
						}
						
					}

					if ($req_details==""){
						//If there are no requests for the technician
						$display_messageInWebex = "Hey ".$user_name1.", You don't have pending requests.";
					}
					else{
						//if there are assigned requests
						$preText = "Hey ".$user_name1.", You have ".sizeof($idArray)." pending requests. ";
						$display_messageInWebex = "**$preText**"."\n".$req_details;
					}

					//send the cotent to the webex teams space
					sendMessageToWebexSpace($display_messageInWebex);

				}
				else{
					$notAllowedMessage = "You are not allowed to view requests. Please contact the administrator...";
					sendMessageToWebexSpace($notAllowedMessage);
				}
				
			/*freeresultset*/
			$result->free();
			}

		break;

		case 'close-ticket-number-ack-closure':


		break;
		
		default:
			$speech = "I didn't get you. Please ask me something else";
			sendMessageToWebexSpace($speech);
			break;
	}
	
	//sendMessageToWebexSpace($speech);
	
}
else{
	echo "Method is invalid";
}

function sendMessageToWebexSpace($messageContent){
	$response = new \stdClass();
	$response -> fulfillmentText= $messageContent;
	$response -> displayText = $messageContent;
	$response -> source = "webhook";
	echo json_encode($response);
}
?>