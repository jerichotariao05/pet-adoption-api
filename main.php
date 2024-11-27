<?php

require_once ('header.php');
require_once ('connection.php');

class Main
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    private function getTypeCount()
    {
        $sql = "SELECT COUNT(*) as total FROM tbl_pet_type";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }

    private function getPetCount()
    {
        $sql = "SELECT COUNT(*) as total FROM tbl_pet";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }

    private function getApprovalCount()
    {
        $sql = "SELECT COUNT(*) as total FROM tbl_adoption_request WHERE status = 'Pending' ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }

    private function getUsersCount()
    {
        $sql = "SELECT COUNT(*) as total FROM tbl_adopter";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }

    public function getCardsData() {
        return json_encode([ "success" =>[
            'types' => $this->getTypeCount(),
            'pets' => $this->getPetCount(),
            'approval' => $this->getApprovalCount(),
            'users' => $this->getUsersCount()
        ]
        ]);
    }

    public function addType($json)
    {
        $decoded_json = json_decode($json, true);
        $sql = "INSERT INTO tbl_pet_type (pet_type_name) 
                VALUES (:type) ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':type', $decoded_json['type']);
        if ($stmt->execute()) {
            return json_encode(["success" => "Pet type added successfully"]);
        } else {
            return json_encode(["error" => "Failed to create pet type: " . $stmt->errorInfo()[2]]);
        }
    }

    public function getPetTypes()
    {
        $sql = "SELECT * FROM tbl_pet_type ORDER BY pet_type_id DESC ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    public function updateType($json)
    {
        $decoded_json = json_decode($json, true);
        $sql = "UPDATE tbl_pet_type SET pet_type_name = :type
                WHERE pet_type_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':type', $decoded_json['type']);
        $stmt->bindParam(':id', $decoded_json['petTypeId']);
        if ($stmt->execute()) {
            return json_encode(["success" => true, "message" => "Pet type updated successfully"]);
        } else {
            return json_encode(["error" => "Failed to update pet type: " . $stmt->errorInfo()[2]]);
        }
    }


    public function addBreed($json)
    {
        $decoded_json = json_decode($json, true);
        $sql = "INSERT INTO tbl_pet_breed (pet_type_id, breed_name) 
                VALUES (:type, :name) ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':type', $decoded_json['type']);
        $stmt->bindParam(':name', $decoded_json['name']);
        if ($stmt->execute()) {
            return json_encode(["success" => "Pet breed added successfully"]);
        } else {
            return json_encode(["error" => "Failed to create pet breed: " . $stmt->errorInfo()[2]]);
        }
    }


    public function getBreeds()
    {
        $sql = "SELECT b.*, t.pet_type_name
                FROM tbl_pet_breed AS b
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = b.pet_type_id 
                ORDER BY b.breed_id DESC
                ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    public function getTypeBreeds($json)
    {

        $decoded_json = json_decode($json, true);

        $sql = "SELECT b.breed_id, b.breed_name
                FROM tbl_pet_breed AS b
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = b.pet_type_id 
                WHERE t.pet_type_id = :pet_type_id
                ORDER BY b.breed_id DESC
                ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':pet_type_id', $decoded_json['pet_type_id']);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    public function updateBreed($json)
    {
        $decoded_json = json_decode($json, true);
        $sql = "UPDATE tbl_pet_breed SET pet_type_id = :type, breed_name = :name
                WHERE breed_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $decoded_json['id']);
        $stmt->bindParam(':type', $decoded_json['type']);
        $stmt->bindParam(':name', $decoded_json['name']);
        if ($stmt->execute()) {
            return json_encode(["success" => true, "message" => "Pet breed updated successfully"]);
        } else {
            return json_encode(["error" => "Failed to update pet breed: " . $stmt->errorInfo()[2]]);
        }
    }



    public function addPet($json){
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['name'],
                    $decoded_json['type'], 
                    $decoded_json['breed'], 
                    $decoded_json['description'], 
                    $decoded_json['age'], 
                    $decoded_json['gender'], 
                    $decoded_json['adoptionFee'], 
                    $decoded_json['status'])) {
            return json_encode(["error" => "Missing required fields"]);
        }

        $uploadDir = "upload/";
        $imagePath = null;
    
        if (!empty($_FILES['image']['name'])) {
            $fileName = uniqid() . '_' . date('YmdHis') . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $fileName;
    
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $imagePath = $fileName; 
            } else {
                return json_encode(array("status" => "failed", "message" => "Failed to save the uploaded file."));
            }
        }


        $sql = "INSERT INTO tbl_pet(pet_name, pet_image, pet_type_id, breed_id,description, age, gender, adoption_fee, adoption_status, created_at) 
                VALUES (:name, :petImage, :type, :breed, :description, :age, :gender, :adoptionFee, :status, CURRENT_TIMESTAMP()) ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $decoded_json['name']);
        $stmt->bindParam(':petImage', $imagePath);
        $stmt->bindParam(':type', $decoded_json['type']);
        $stmt->bindParam(':breed', $decoded_json['breed']);
        $stmt->bindParam(':description', $decoded_json['description']);
        $stmt->bindParam(':age', $decoded_json['age']);
        $stmt->bindParam(':gender', $decoded_json[' ']);
        $stmt->bindParam(':adoptionFee', $decoded_json['adoptionFee']);
        $stmt->bindParam(':status', $decoded_json['status']);

        if ($stmt->execute()) {
            return json_encode(["success" => "Pet added successfully"]);
        } else {
            return json_encode(["error" => "Failed to create pet: " . $stmt->errorInfo()[2]]);
        }
    }


    public function getPets()
    {
        $sql = "SELECT p.*, t.pet_type_name, b.breed_name
                FROM tbl_pet AS p
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
                INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id 
                ORDER BY p.pet_id DESC
                ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    public function filterAvailablePets($json)
    {
        $decoded_json = json_decode($json, true);

        
        if (isset($decoded_json["petType"]) && $decoded_json["petType"] !== null) {
            $petType = $decoded_json["petType"];
    
            $sql = "SELECT p.*, t.pet_type_name, b.breed_name
                FROM tbl_pet AS p
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
                INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
                WHERE p.adoption_status = 'Available' AND t.pet_type_id = :petType
                ORDER BY p.pet_id DESC ";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':petType', $petType, PDO::PARAM_INT);
            
        } else {
            $sql = "SELECT p.*, t.pet_type_name, b.breed_name
                FROM tbl_pet AS p
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
                INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
                WHERE p.adoption_status = 'Available'
                ORDER BY p.pet_id DESC";
            $stmt = $this->conn->prepare($sql);
        }
        
        if ($stmt->execute()) {
            $petsInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(array("success" => $petsInfo));
        } else {
            return json_encode(["error" => "Failed to retrieve data from the database"]);
        }

    }

    public function petProfile($json)
    {

        $decoded_json = json_decode($json, true);
        $petId = $decoded_json["petId"];

        $sql = "SELECT p.*, t.pet_type_name, b.breed_name
                FROM tbl_pet AS p
                INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
                INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
                WHERE p.pet_id = :petId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':petId', $petId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    private function banUser($attempt = false, $notCanceled = false, $adopterId) {
        $limit = 3;
    
        $sql = "SELECT * FROM banned_adopters WHERE adopter_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
    
        if ($stmt) {
            $stmt->bindParam(":id", $adopterId);
            $stmt->execute();
    
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($row) { 
                $time = time();
                if ($row['banned'] > $time) {
                    return "You are currently prevented from adopting a pet in because you have reached the maximum number of cancel adoption request attempts. Until then please wait for 5 days until the banned is uplifted.";
                }
    
                if ($attempt) {
                    if ($row['cancel_count'] >= $limit) {
    
                        $expire = ($time + (60 * 60 * 24 * 5)); 
                        $updateSql = "UPDATE banned_adopters SET banned = :banned, cancel_count = 1 WHERE attempt_id = :id LIMIT 1";
                        $updateStmt = $this->conn->prepare($updateSql);
                        $updateStmt->bindParam(":id", $row['attempt_id']);
                        $updateStmt->bindParam(":banned", $expire);
                        $updateStmt->execute();
                        return; 
                    } 
    
                    if ($notCanceled) {
                        $updateSql = "UPDATE banned_adopters SET cancel_count = 0 WHERE attempt_id = :id LIMIT 1";
                    } else {
                        $updateSql = "UPDATE banned_adopters SET cancel_count = cancel_count + 1 WHERE attempt_id = :id LIMIT 1";
                    }
    
                    $updateStmt = $this->conn->prepare($updateSql);
                    $updateStmt->bindParam(":id", $row['attempt_id']);
                    $updateStmt->execute();
                }
            } else {
                $cancelCount = 0;
                $banned = 0;
                $banUserSql = "INSERT INTO banned_adopters (adopter_id, cancel_count, banned) VALUES (:id, :cancelCount, :banned)";
                $banStmt = $this->conn->prepare($banUserSql);
                $banStmt->bindParam(":id", $adopterId);
                $banStmt->bindParam(":cancelCount", $cancelCount);
                $banStmt->bindParam(":banned", $banned);
                $banStmt->execute();
            }
        } 
    }
    

    public function adoptionRequest($json) {

        $decoded_json = json_decode($json, true);
        $status = "Pending";
        $petStatus = "Pending";
        $adopterId = $decoded_json['adopterId'];

        $checkSql = "SELECT status FROM tbl_adopter WHERE adopter_id = :adopterId ";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(":adopterId", $adopterId);
        $checkStmt->execute();
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $adopterStatus = $row['status'];

        if($adopterStatus === "Deactivate") {
            return json_encode(["error" => "Your account has been deactivated due to suspicious activities and so you are unable to adopt pets at the moment; please contact support if you believe this is a mistake."]);
        }

        $bannedMessage = $this->banUser(false, false, $adopterId);

        if ($bannedMessage) {
            return json_encode(["error" => $bannedMessage]);
        }

        $sqlCheckProfile = "SELECT profile_id, adopter_id FROM tbl_adopter_profile WHERE adopter_id = :adopterId ";
        $stmtCheckProfile = $this->conn->prepare($sqlCheckProfile);
        $stmtCheckProfile->bindParam(":adopterId", $adopterId);
        $stmtCheckProfile->execute();

        if ($stmtCheckProfile->rowCount() === 0) {
            return json_encode(["error" => "You have to fill out and submit the form in the preference page to adopt."]);
        } 

        $row = $stmtCheckProfile->fetch(PDO::FETCH_ASSOC);
        $profileId = $row['profile_id'];
        $today = date('Y-m-d');

        $sqlCheckRequests = "SELECT COUNT(*) as count
                            FROM tbl_adoption_request AS r 
                            INNER JOIN tbl_adopter_profile AS p ON r.profile_id = p.profile_id
                            INNER JOIN tbl_adopter AS a ON p.adopter_id = a.adopter_id
                            WHERE a.adopter_id = :adopterId AND DATE(r.request_date) = :today";
        $stmtCheckRequests = $this->conn->prepare($sqlCheckRequests);
        $stmtCheckRequests->bindParam(":adopterId", $adopterId);
        $stmtCheckRequests->bindParam(":today", $today);
        $stmtCheckRequests->execute();

        $requestRow = $stmtCheckRequests->fetch(PDO::FETCH_ASSOC);
        $requestCount = $requestRow['count'] ?? 0; 
        
        if ($requestCount >= 5) {
            return json_encode([
                "error" => "You have reached the maximum limit of 5 pet adoptions for today. Please try again tomorrow or contact support if you need assistance."
            ]);
        }


        $sql = "INSERT INTO tbl_adoption_request (pet_id, profile_id, request_date, status, approval_date)  
                VALUES (:petId, :profileId, CURRENT_TIMESTAMP(), :status, NULL)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':petId', $decoded_json['petId']);
        $stmt->bindParam(':profileId', $profileId);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {

            $requestId = $this->conn->lastInsertId();

            $insertSql = "INSERT INTO adoption_process (pet_id, adopter_id, adoption_request_id)  VALUES (:petId, :adopterId, :requestId)";
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bindParam(':petId', $decoded_json['petId']);
            $insertStmt->bindParam(':adopterId', $adopterId);
            $insertStmt->bindParam(':requestId', $requestId);

            if ($insertStmt->execute()) {
                    $updateStmt = $this->conn->prepare('UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId');
                    $updateStmt->bindParam(":petId", $decoded_json['petId']);
                    $updateStmt->bindParam(":petStatus", $petStatus);
                    $updateStmt->execute();
            }  else {
                return json_encode(["error" => "Failed to create adoption request: " . $stmt->errorInfo()[2]]);
            }

            return json_encode(["success" => "Adoption request is successful."]);
        } else {
            return json_encode(["error" => "Failed to create adoption request: " . $stmt->errorInfo()[2]]);
        }
    }


    public function getAdoptionRequests()
    {
            $sql = "SELECT r.*, p.*, ap.*,t.pet_type_name, b.breed_name, a.first_name, a.last_name, a.avatar, a.contact_number, a.address, a.email
            FROM tbl_adoption_request AS r
            INNER JOIN tbl_pet AS p ON p.pet_id = r.pet_id
            INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
            INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
            INNER JOIN tbl_adopter_profile AS ap ON ap.profile_id = r.profile_id
            INNER JOIN tbl_adopter AS a ON a.adopter_id = ap.adopter_id
            ORDER BY r.adoption_request_id DESC ";
            $stmt = $this->conn->prepare($sql);
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        } else {
            return json_encode(["error" => "Failed to retrieve data from the database"]);
        }
    }

    public function getAdopterRequests($json)
    {

        $decoded_json = json_decode($json, true);

            $sql = "SELECT  p.*, t.pet_type_name, b.breed_name, a.adopter_id, adp.id, adp.current_phase, adp.request_status, adp.requested_at, r.adoption_request_id
            FROM adoption_process AS adp
            INNER JOIN tbl_pet AS p ON p.pet_id = adp.pet_id
            INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
            INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
            INNER JOIN tbl_adopter AS a ON a.adopter_id = adp.adopter_id
            INNER JOIN tbl_adoption_request AS r ON r.adoption_request_id = adp.adoption_request_id
            WHERE a.adopter_id = :adopterId
            ORDER BY adp.id DESC ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":adopterId", $decoded_json["adopterId"]);
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        } else {
            return json_encode(["error" => "Failed to retrieve data from the database"]);
        }
    }

    public function getRequestedPet($json)
    {

        $decoded_json = json_decode($json, true);

            $sql = "SELECT  p.*, t.pet_type_name, b.breed_name, a.adopter_id, adp.*
            FROM adoption_process AS adp
            INNER JOIN tbl_pet AS p ON p.pet_id = adp.pet_id
            INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
            INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
            INNER JOIN tbl_adopter AS a ON a.adopter_id = adp.adopter_id
            INNER JOIN tbl_adoption_request AS r ON r.adoption_request_id = adp.adoption_request_id
            WHERE adp.adoption_request_id = :requestId
            ORDER BY adp.id DESC ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":requestId", $decoded_json["requestId"]);
        if ($stmt->execute()) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        } else {
            return json_encode(["error" => "Failed to retrieve data from the database"]);
        }
    }


    public function cancelAdoptionRequest($json)
    {
        $decoded_json = json_decode($json, true);
        $status = "Canceled";
        $petStatus = "Available";
        $phase = "Cancelled";

        $bannedMessage = $this->banUser(true, false, $decoded_json['adopterId']);

        if ($bannedMessage) {
            return json_encode(["error" => $bannedMessage]);
        }
        
        $sql = "UPDATE tbl_adoption_request SET status = :status WHERE adoption_request_id = :requestId ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':requestId', $decoded_json['requestId']);

        if ($stmt->execute()) {
            $updateStmt = $this->conn->prepare('UPDATE adoption_process SET current_phase = :phase, request_status = :requestStatus WHERE adoption_request_id = :requestId');
            $updateStmt->bindParam(":phase", $phase);
            $updateStmt->bindParam(":requestStatus", $phase);
            $updateStmt->bindParam(':requestId', $decoded_json['requestId']);
            if($updateStmt->execute()) {
                $sqlUpdate = "UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId ";
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':petStatus', $petStatus);
                $stmtUpdate->bindParam(':petId', $decoded_json['petId']);
                $stmtUpdate->execute();

            return json_encode(["success" => "Adoption request canceled successfully"]);
            } else {
                return json_encode(["error" => "Something went wrong:" . $stmt->errorInfo()[2]]);
            }
        } else {
            return json_encode(["error" => "Failed to cancel adoption request: " . $stmt->errorInfo()[2]]);
        }
    }

    public function adoptionApproval($json)
    {
        $decoded_json = json_decode($json, true);

        $petName = $decoded_json["petName"]; 
        $firstName = $decoded_json["firstName"]; 
        $lastName = $decoded_json["lastName"]; 
        $recipientName = $firstName . " " . $lastName; 
        $email = $decoded_json["email"]; 
        $interviewDatetime = $decoded_json["interviewDatetime"];
        $formattedDatetime = str_replace("T", " ", $interviewDatetime);
        $status = "Approved";
        $interviewStatus = "Pending";
        $requestStatus = "Accepted";
        $phase = "Interview";
        
        $subject = "Adoption Request Approved - Interview Scheduled";

        $message = "
        Dear $recipientName,
        
        We are pleased to inform you that your adoption request for <strong>$petName</strong> has been accepted. 
        
        Your interview has been scheduled for <strong>$formattedDatetime</strong>. Please ensure to arrive on time and please bring the following the documents or IDs for verification: Philippine National ID (PhilID), Passport, Driver's License, Government-issued ID (e.g., SSS, GSIS, Postal ID).

        Having these documents ready will help us expedite your interview.
        
        We look forward to meeting you and discussing the next steps towards welcoming <strong>$petName</strong> into your home.
        
        This is a system-generated message. Please do not reply to this email.
        
        Best regards,  
        The Adoption Team  
        Pet Adoption Shelter";

        $to = $email;
        $headers = "From: no-reply@petadoptionapp.com\r\n";
        $headers .= "Reply-To: no-reply@petadoptionapp.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n"; 
        
        $sql = "UPDATE tbl_adoption_request SET status = :status, approval_date = NOW() 
        WHERE adoption_request_id = :requestId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':requestId', $decoded_json['requestId']);
        if ($stmt->execute()) {  
            $sqlSchedule = "INSERT INTO tbl_interview (adoption_request_id, interview_datetime, status) VALUES (:requestId, :interviewDatetime, :status)";
            $stmtSchedule = $this->conn->prepare($sqlSchedule);
            $stmtSchedule->bindParam(':requestId', $decoded_json['requestId']);
            $stmtSchedule->bindParam(':interviewDatetime', $interviewDatetime);
            $stmtSchedule->bindParam(':status', $interviewStatus);
            if ($stmtSchedule->execute()) { 

                if(mail($to, $subject, nl2br($message), $headers)){
                    $updateStmt = $this->conn->prepare('UPDATE adoption_process SET request_status = :requestStatus, interview_scheduled_at = :interviewDatetime, review_completed_at = CURRENT_TIMESTAMP(), current_phase = :phase WHERE adoption_request_id = :requestId');
                    $updateStmt->bindParam(":requestStatus", $requestStatus);
                    $updateStmt->bindParam(":interviewDatetime", $interviewDatetime);
                    $updateStmt->bindParam(":phase", $phase);
                    $updateStmt->bindParam(':requestId', $decoded_json['requestId']);
                    $updateStmt->execute();
                    return json_encode(["success" => "Adoption request approved"]);
                } else {
                    return json_encode(["error" => "Email sending failed"]);
                }
            } else {
                return json_encode(["error" => "Failed to add interview schedule: " . $stmtSchedule->errorInfo()[2]]);
            }
        } else {
            return json_encode(["error" => "Failed to approve adoption request: " . $stmt->errorInfo()[2]]);
        }
    }

    public function adoptionRejected($json)
    {
        $decoded_json = json_decode($json, true);

        $petName = $decoded_json["petName"]; 
        $firstName = $decoded_json["firstName"]; 
        $lastName = $decoded_json["lastName"]; 
        $recipientName = $firstName . " " . $lastName; 
        $email = $decoded_json["email"]; 
        $status = "Rejected";
        $petStatus = "Available";
        
        $subject = "Adoption Request Rejected";


        $message = "
        Dear $recipientName,

        We regret to inform you that your adoption request for <strong>$petName</strong> has been rejected. 

        Please understand that the decision was made based on a thorough evaluation process, and we appreciate your interest in adopting <strong>$petName</strong>.

        If you have any further questions or would like to explore other adoption opportunities, feel free to contact us directly.

        This is a system-generated message. Please do not reply to this email.

        Best regards,  
        The Adoption Team  
        Pet Adoption Shelter";

        $to = $email;

        $headers = "From: no-reply@petadoptionapp.com\r\n";
        $headers .= "Reply-To: no-reply@petadoptionapp.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $sql = "UPDATE tbl_adoption_request SET status = :status WHERE adoption_request_id = :requestId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':requestId', $decoded_json['requestId']);
        if ($stmt->execute()) {  
                if(mail($to, $subject, nl2br($message), $headers)){

                    $updateStmt = $this->conn->prepare('UPDATE adoption_process SET request_status = :requestStatus, review_completed_at = CURRENT_TIMESTAMP() WHERE adoption_request_id = :requestId');
                    $updateStmt->bindParam(":requestStatus", $status);
                    $updateStmt->bindParam(':requestId', $decoded_json['requestId']);

                    if($updateStmt->execute()) {
                        $petStatusStmt = $this->conn->prepare('UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId');
                        $petStatusStmt->bindParam(":petId", $decoded_json['petId']);
                        $petStatusStmt->bindParam(":petStatus", $petStatus);
                        $petStatusStmt->execute();
                        return json_encode(["success" => "Adoption request rejected"]);
                    } else {
                        return json_encode(["error" => "Something went wrong: " . $stmt->errorInfo()[2]]);
                    }

                } else {
                    return json_encode(["error" => "Email sending failed"]);
                }
        } else {
            return json_encode(["error" => "Failed to reject adoption request: " . $stmt->errorInfo()[2]]);
        }
    }



    public function getUsers()
    {
        $sql = "SELECT adopter_id, first_name, last_name, contact_number, email,
        address, avatar, username, status, created_at FROM tbl_adopter ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }


    public function updateProfile($json)
    {
        $decoded_json = json_decode($json, true);

        $sqlCheckProfile = "SELECT adopter_id FROM tbl_adopter_profile WHERE adopter_id = :adopterId ";
        $stmtCheckProfile = $this->conn->prepare($sqlCheckProfile);
        $stmtCheckProfile->bindParam(":adopterId", $decoded_json['adopterId']);
        $stmtCheckProfile->execute();

        if ($stmtCheckProfile->rowCount() > 0) {
            $sql = "UPDATE tbl_adopter_profile SET household_size = :householdSize, home_type = :homeType, salary = :salary, live_with = :liveWith, availability_to_care = :availabilityToCare, other_pets = :otherPets, pet_experiences = :petExperiences WHERE adopter_id = :adopterId ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':householdSize', $decoded_json['householdSize']);
            $stmt->bindParam(':homeType', $decoded_json['homeType']);
            $stmt->bindParam(':salary', $decoded_json['salary']);
            $stmt->bindParam(':liveWith', $decoded_json['liveWith']);
            $stmt->bindParam(':availabilityToCare', $decoded_json['availabilityToCare']);
            $stmt->bindParam(':otherPets', $decoded_json['otherPets']);
            $stmt->bindParam(':petExperiences', $decoded_json['petExperiences']);
            $stmt->bindParam(':adopterId', $decoded_json['adopterId']);
            if ($stmt->execute()) {
                return json_encode(["success" => "Profile for adoption updated successfully"]);
            } else {
                return json_encode(["error" => "Failed to update profile for adoption: " . $stmt->errorInfo()[2]]);
            }
        } else {
            $sql = "INSERT INTO tbl_adopter_profile (adopter_id, household_size, home_type, salary, live_with, availability_to_care, other_pets, pet_experiences) VALUES (:adopterId, :householdSize, :homeType, :salary, :liveWith, :availabilityToCare, :otherPets, :petExperiences) ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':adopterId', $decoded_json['adopterId']);
            $stmt->bindParam(':householdSize', $decoded_json['householdSize']);
            $stmt->bindParam(':homeType', $decoded_json['homeType']);
            $stmt->bindParam(':salary', $decoded_json['salary']);
            $stmt->bindParam(':liveWith', $decoded_json['liveWith']);
            $stmt->bindParam(':availabilityToCare', $decoded_json['availabilityToCare']);
            $stmt->bindParam(':otherPets', $decoded_json['otherPets']);
            $stmt->bindParam(':petExperiences', $decoded_json['petExperiences']);
            if ($stmt->execute()) {
                return json_encode(["success" => "Profile for adoption updated successfully"]);
            } else {
                return json_encode(["error" => "Failed to update profile for adoption: " . $stmt->errorInfo()[2]]);
            }
        }
    }

    public function getAdopterBasicInfo($json)
    {
        $decoded_json = json_decode($json, true);
        $sql = "SELECT * FROM tbl_adopter WHERE adopter_id = :adopterId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':adopterId', $decoded_json['adopterId']);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            unset($result['password']);
            return json_encode(["success" => $result]);
        }
    }

    public function getAdopterProfile($json)
    {
        $decoded_json = json_decode($json, true);

        $sql = "SELECT ap.* FROM tbl_adopter_profile AS ap
                INNER JOIN tbl_adopter AS a ON a.adopter_id = ap.adopter_id
                WHERE a.adopter_id = :adopterId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':adopterId', $decoded_json['adopterId']);

        if ($stmt->execute()) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_encode(array("success" => $row));
        } else {
            return json_encode(["error" => "Failed to retrieve profile for adoption: " . $stmt->errorInfo()[2]]);
        }
    }

    

    public function getInterviews()
    {
        $sql = "SELECT r.adoption_request_id, p.*, t.pet_type_name, b.breed_name, a.adopter_id, a.first_name, a.last_name, a.email,i.*
            FROM tbl_interview AS i
            INNER JOIN tbl_adoption_request AS r ON i.adoption_request_id = r.adoption_request_id
            INNER JOIN tbl_pet AS p ON p.pet_id = r.pet_id
            INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
            INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
            INNER JOIN tbl_adopter_profile AS ap ON ap.profile_id = r.profile_id
            INNER JOIN tbl_adopter AS a ON a.adopter_id = ap.adopter_id
            ORDER BY i.interview_id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    public function passedInterview($json)
    {
        $decoded_json = json_decode($json, true);
        $status = 'Passed';
        $petStatus = 'Adopted';

        $petName = $decoded_json["petName"]; 
        $firstName = $decoded_json["firstName"]; 
        $lastName = $decoded_json["lastName"]; 
        $recipientName = $firstName . " " . $lastName; 
        $email = $decoded_json["email"]; 
        $adoptionDate = $decoded_json['adoptionDate'];
        $pickupDate = $decoded_json['pickupDate'];
        $adoptionStatus = "Adoption successful";
        $interviewStatus = "Passed";
        $phase = "Adoption_completion";

        $subject = "Adoption Interview Outcome";
        $message = "
        Dear $recipientName,
        
        We are pleased to inform you that you have successfully passed the interview process for adopting <strong>$petName</strong>! 
        
        Your tentative adoption date is <strong>$adoptionDate</strong>, when we will finalize all necessary documents. You can pick up <strong>$petName</strong> on <strong>$pickupDate</strong>.
        
        We appreciate your commitment and enthusiasm toward providing a loving home for <strong>$petName</strong>. If you have any questions or need further assistance, please do not hesitate to contact us.
        
        This is a system-generated message. Please do not reply to this email.
        
        Best regards,  
        The Adoption Team  
        Pet Adoption Shelter";
        
        $to = $email;
        
        $headers = "From: no-reply@petadoptionapp.com\r\n";
        $headers .= "Reply-To: no-reply@petadoptionapp.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $sql = "UPDATE tbl_interview SET status = :status WHERE interview_id = :interviewId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':interviewId', $decoded_json['interviewId']);
        $stmt->bindParam(':status', $status);
        if ($stmt->execute()) {

            if(mail($to, $subject, nl2br($message), $headers)){
                $insertSql = "INSERT INTO tbl_adoption_history (pet_id, adopter_id, adoption_date, pickup_date, created_at) VALUES (:petId, :adopterId, :adoptionDate, :pickupDate,  CURRENT_TIMESTAMP()) ";
                $insertStmt = $this->conn->prepare($insertSql);
                $insertStmt->bindParam(':petId', $decoded_json['petId']);
                $insertStmt->bindParam(':adopterId', $decoded_json['adopterId']);
                $insertStmt->bindParam(':adoptionDate', $adoptionDate);
                $insertStmt->bindParam(':pickupDate', $pickupDate);
                if ($insertStmt->execute()) {

                    $updateStmt = $this->conn->prepare('UPDATE adoption_process SET adoption_status = :adoptionStatus, interview_status = :interviewStatus, pickup_date = :pickupDate, current_phase = :phase WHERE adoption_request_id = :requestId');
                    $updateStmt->bindParam(":adoptionStatus", $adoptionStatus);
                    $updateStmt->bindParam(":interviewStatus", $interviewStatus);
                    $updateStmt->bindParam(":pickupDate", $pickupDate);
                    $updateStmt->bindParam(":phase", $phase);
                    $updateStmt->bindParam(':requestId', $decoded_json['requestId']);

                    if($updateStmt->execute()) {
                        $petStatusStmt = $this->conn->prepare('UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId');
                        $petStatusStmt->bindParam(":petId", $decoded_json['petId']);
                        $petStatusStmt->bindParam(":petStatus", $petStatus);
                        $petStatusStmt->execute();
                        return json_encode(["success" => true, "message" => "Interview status updated successfully"]);
                    } else {
                            return json_encode(["error" => "Something went wrong: " . $updateStmt->errorInfo()[2]]);
                    }
                  
                } else {
                    return json_encode(["error" => "Failed to update interview status: " . $insertStmt->errorInfo()[2]]);
                }
            } else {
                return json_encode(["error" => "Email sending failed"]);
            }
        } else {
            return json_encode(["error" => "Failed to update interview status: " . $stmt->errorInfo()[2]]);
        }
    }

    public function failedInterview($json)
    {
        $decoded_json = json_decode($json, true);
        $status = 'Failed';
        $petStatus = 'Available';

        $petName = $decoded_json["petName"]; 
        $firstName = $decoded_json["firstName"]; 
        $lastName = $decoded_json["lastName"]; 
        $recipientName = $firstName . " " . $lastName; 
        $email = $decoded_json["email"]; 
        $interviewStatus = "Failed";

        $subject = "Adoption Interview Outcome";
        $message = "
        Dear $recipientName,

        Thank you for your interest in adopting <strong>$petName</strong>. We appreciate the time you took to meet with us; however, we regret to inform you that your application was not successful.

        This decision was made after careful consideration of your interview. We encourage you to explore other adoption opportunities in the future.

        If you have any questions or would like to discuss this further, please feel free to reach out.

        This is a system-generated message. Please do not reply to this email.

        Best regards,  
        The Adoption Team  
        Pet Adoption Shelter";

        $to = $email;

        $headers = "From: no-reply@petadoptionapp.com\r\n";
        $headers .= "Reply-To: no-reply@petadoptionapp.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $sql = "UPDATE tbl_interview SET status = :status WHERE interview_id = :interviewId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':interviewId', $decoded_json['interviewId']);
        $stmt->bindParam(':status', $status);
        if ($stmt->execute()) {
            
            $updateStmt = $this->conn->prepare('UPDATE adoption_process SET interview_status = :interviewStatus WHERE adoption_request_id = :requestId');
            $updateStmt->bindParam(":interviewStatus", $interviewStatus);
            $updateStmt->bindParam(':requestId', $decoded_json['requestId']);

            if($updateStmt->execute()) {
                if(mail($to, $subject, nl2br($message), $headers)){
                    $updateStmt = $this->conn->prepare('UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId');
                    $updateStmt->bindParam(":petId", $decoded_json['petId']);
                    $updateStmt->bindParam(":petStatus", $petStatus);
                    $updateStmt->execute();
                    return json_encode(["success" => "Interview status updated successfully"]);
                } else {
                    return json_encode(["error" => "Email sending failed"]);
                }
            } else {
                return json_encode(["error" => "Something went wrong: " . $updateStmt->errorInfo()[2]]);
            }
           
        } else {
            return json_encode(["error" => "Failed to update interview status: " . $stmt->errorInfo()[2]]);
        }
    }

    public function noShowInterview($json)
    {
        $decoded_json = json_decode($json, true);
        $status = 'No-show';
        $petStatus = 'Available';

        $petName = $decoded_json["petName"]; 
        $firstName = $decoded_json["firstName"]; 
        $lastName = $decoded_json["lastName"]; 
        $recipientName = $firstName . " " . $lastName; 
        $email = $decoded_json["email"]; 
        $interviewStatus = "No show";

        $subject = "Adoption Interview Outcome";
        $message = "
        Dear $recipientName,
        
        We regret to inform you that we were unable to proceed with your adoption request for <strong>$petName</strong> due to a no-show for your scheduled interview.
        
        We understand that circumstances can change, and we encourage you to reach out if you would like to reschedule the interview or discuss other adoption opportunities.
        
        Thank you for your interest in providing a loving home for <strong>$petName</strong>.
        
        This is a system-generated message. Please do not reply to this email.
        
        Best regards,  
        The Adoption Team  
        Pet Adoption Shelter";

        $to = $email;

        $headers = "From: no-reply@petadoptionapp.com\r\n";
        $headers .= "Reply-To: no-reply@petadoptionapp.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $sql = "UPDATE tbl_interview SET status = :status WHERE interview_id = :interviewId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':interviewId', $decoded_json['interviewId']);
        $stmt->bindParam(':status', $status);
        if ($stmt->execute()) {
            $updateStmt = $this->conn->prepare('UPDATE adoption_process SET interview_status = :interviewStatus WHERE adoption_request_id = :requestId');
            $updateStmt->bindParam(":interviewStatus", $interviewStatus);
            $updateStmt->bindParam(':requestId', $decoded_json['requestId']);
            
            if($updateStmt->execute()) {
                if(mail($to, $subject, nl2br($message), $headers)){
                    $updateStmt = $this->conn->prepare('UPDATE tbl_pet SET adoption_status = :petStatus WHERE pet_id = :petId');
                    $updateStmt->bindParam(":petId", $decoded_json['petId']);
                    $updateStmt->bindParam(":petStatus", $petStatus);
                    $updateStmt->execute();
                    return json_encode(["success" => "Interview status updated successfully"]);
                } else {
                    return json_encode(["error" => "Email sending failed"]);
                }
            } else {
                return json_encode(["error" => "Something went wrong: " . $updateStmt->errorInfo()[2]]);
            }
        } else {
            return json_encode(["error" => "Failed to update interview status: " . $stmt->errorInfo()[2]]);
        }
    }

    public function getAdoptionHistory()
    {
        $sql = "SELECT p.*, t.pet_type_name, b.breed_name, a.first_name, a.last_name, a.email, a.contact_number, h.*
            FROM tbl_adoption_history AS h
            INNER JOIN tbl_pet AS p ON p.pet_id = h.pet_id
            INNER JOIN tbl_pet_type AS t ON t.pet_type_id = p.pet_type_id 
            INNER JOIN tbl_pet_breed AS b ON b.breed_id = p.breed_id
            INNER JOIN tbl_adopter AS a ON a.adopter_id = h.adopter_id
            ORDER BY h.adoption_id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(["success" => $result]);
        }

    }

    function updateAvatar($json) {
    
        $decoded_json = json_decode($json, true);
    
        $uploadDir = "avatar/";
        $avatarPath = null;
    
        if (!empty($_FILES['image']['name'])) {
            $fileName = uniqid() . '_' . date('YmdHis') . '_' . basename($_FILES['image']['name']);
            $uploadFile = $uploadDir . $fileName;
    
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $avatarPath = $fileName; 
            } else {
                return json_encode(array("error" => "Failed to save the uploaded file."));
            }
        }

        $sql = "UPDATE tbl_adopter SET avatar = :avatar WHERE adopter_id = :adopterId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':avatar', $avatarPath);
        $stmt->bindParam(':adopterId', $decoded_json["adopterId"]);
        if ( $stmt->execute()) {
            return json_encode(["success" => "Avatar updated successfully"]);
        } else {
            return json_encode(["error" => "Failed to update avatar: " . $stmt->errorInfo()[2]]);
        }
    }


    public function deactivateAdopter($json)
    {
        $decoded_json = json_decode($json, true);
        $status = "Deactivate";
        $sql = "UPDATE tbl_adopter SET status = :status WHERE adopter_id = :adopterId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':adopterId', $decoded_json['adopterId']);
        if ($stmt->execute()) {
            return json_encode(["success" => "Adopter deactivated successfully"]);
        } else {
            return json_encode(["error" => "Failed to deactivate adopter: " . $stmt->errorInfo()[2]]);
        }
    }

}

$main = new Main();

if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            // Dashboard
            case 'getCardsData':
                echo $main->getCardsData();
                break;
            
            //Pet Types Add, View, Update
            case 'addType':
                echo $main->addType($json);
                break;
            case 'getPetTypes':
                echo $main->getPetTypes();
                break;
            case 'updateType':
                echo $main->updateType($json);
                break;

            //Breed Add, View, Update
            case 'addBreed':
                echo $main->addBreed($json);
                break;
            case 'getBreeds':
                echo $main->getBreeds();
                break;
            case 'getTypeBreeds':
                echo $main->getTypeBreeds($json);
                break;
            case 'updateBreed':
                echo $main->updateBreed($json);
                break;

            //Pet Add, View
            case 'addPet':
                echo $main->addPet($json);
                break;
            case 'getPets':
                echo $main->getPets();
                break;
            case 'filterAvailablePets':
                echo $main->filterAvailablePets($json);
                break;
            case 'petProfile':
                echo $main->petProfile($json);
                break;
            
            //Adoption request Add, View, Accept, Reject, Cancel
            case 'adoptionRequest':
                echo $main->adoptionRequest($json);
                break;
            case 'cancelAdoptionRequest':
                echo $main->cancelAdoptionRequest($json);
                break;
            case 'getAdoptionRequests':
                echo $main->getAdoptionRequests();
                break;
            case 'getAdopterRequests':
                echo $main->getAdopterRequests($json);
                break;
            case 'getRequestedPet':
                echo $main->getRequestedPet($json);
                break;
            case 'adoptionApproval':
                echo $main->adoptionApproval($json);
                break;
            case 'adoptionRejected':
                echo $main->adoptionRejected($json);
                break;

            //Users  View
            case 'getUsers':
                echo $main->getUsers();
                break;

            //Adopter profile/preference  Add or Update, View
            case 'updateProfile':
                echo $main->updateProfile($json);
                break;
            case 'getAdopterBasicInfo':
                echo $main->getAdopterBasicInfo($json);
                break;
            case 'getAdopterProfile':
                echo $main->getAdopterProfile($json);
                break;

            //Interview View, Passed, Failed, No-show 
            case 'getInterviews':
                echo $main->getInterviews();
                break;
            case 'passedInterview':
                echo $main->passedInterview($json);
                break;
            case 'failedInterview':
                echo $main->failedInterview($json);
                break;
            case 'noShowInterview':
                echo $main->noShowInterview($json);
                break;

            //Adoption History View
            case 'getAdoptionHistory':
                echo $main->getAdoptionHistory();
                break;

            //Update adopter avatar
            case 'updateAvatar':
                echo $main->updateAvatar($json);
                break;
        
            //Update adopter status
            case 'deactivateAdopter':
                echo $main->deactivateAdopter($json);
                break;

            default:
                echo json_encode(["error" => "Invalid operation"]);
                break;
        }
    } else {
        echo json_encode(["error" => "Missing parameters"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}
?>