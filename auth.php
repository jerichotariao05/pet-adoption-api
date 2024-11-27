<?php

require_once ('header.php');
require_once ('connection.php');

class Auth
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function adopterSignup($json)
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['firstName'],
                    $decoded_json['lastName'], 
                    $decoded_json['contactNum'], 
                    $decoded_json['email'], 
                    $decoded_json['address'], 
                    $decoded_json['username'], 
                    $decoded_json['password'])) {
            return json_encode(["error" => "Missing required fields"]);
        }

        $firstName = $decoded_json['firstName'];
        $lastName = $decoded_json['lastName'];
        $contactNum = $decoded_json['contactNum'];
        $email = $decoded_json['email'];
        $address = $decoded_json['address'];
        $username = $decoded_json['username'];
        $password = $decoded_json['password'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sqlCheck = "SELECT email FROM tbl_adopter WHERE email = :email ";
        $checkStmt = $this->conn->prepare($sqlCheck);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            return json_encode(["error" => "Email address already exists"]);
        }

        $sql = "INSERT INTO tbl_adopter (first_name, last_name, contact_number, email, address, username, password, created_at) 
                VALUES (:firstName, :lastName, :contactNum, :email, :address, :username, :password, CURRENT_TIMESTAMP()) ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':firstName', $firstName);
        $stmt->bindParam(':lastName', $lastName);
        $stmt->bindParam(':contactNum', $contactNum);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);

        if ($stmt->execute()) {
            return json_encode(["success" => "User registration Success!"]);
        } else {
            return json_encode(["error" => "Signup failed: " . $stmt->errorInfo()[2]]);
        }
    }

    public function adopterLogin($json)
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['email']) || !isset($decoded_json['password'])) {
            return json_encode(["error" => "Missing required fields"]);
        }

        $email = $decoded_json['email'];
        $password = $decoded_json['password'];

        $checkSql = "SELECT * FROM tbl_adopter WHERE email = :email ";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(":email", $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) {
            return json_encode(["error" => "Invalid email"]);
        }
        
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $status = $row['status'];
        $hashedPasswordFromDB = $row['password'];

        if (password_verify($password, $hashedPasswordFromDB)) {
            unset($row['password']);
            if($status === "Deactivated") {
                return json_encode(["error" => "Your account has been deactivated due to suspicious activities; please contact support if you believe this is a mistake."]);
            }
            return json_encode(array("success" => $row));
        } else {
            return json_encode(["error" => "Invalid password"]);
        }
    }

 

    public function adminSignup($json)
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['firstName'],
                    $decoded_json['lastName'], 
                    $decoded_json['email'], 
                    $decoded_json['password'])) {
            return json_encode(["error" => "Missing required fields"]);
        }

        $firstName = $decoded_json['firstName'];
        $lastName = $decoded_json['lastName'];
        $email = $decoded_json['email'];
        $password = $decoded_json['password'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sqlCheck = "SELECT email FROM tbl_admin WHERE email = :email ";
        $checkStmt = $this->conn->prepare($sqlCheck);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            return json_encode(["error" => "Admin already exists"]);
        }

        $sql = "INSERT INTO tbl_admin (first_name, last_name, email, password) 
                VALUES (:firstName, :lastName, :email, :password) ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':firstName', $firstName);
        $stmt->bindParam(':lastName', $lastName);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);

        if ($stmt->execute()) {
            return json_encode(["success" => "Admin registration Success!"]);
        } else {
            return json_encode(["error" => "Signup failed: " . $stmt->errorInfo()[2]]);
        }
    }

    public function adminLogin($json)
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['email']) || !isset($decoded_json['password'])) {
            return json_encode(["error" => "Missing required fields"]);
        }

        $email = $decoded_json['email'];
        $password = $decoded_json['password'];

        $checkSql = "SELECT * FROM tbl_admin WHERE email = :email ";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(":email", $email);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) {
            return json_encode(["error" => "Invalid email"]);
        }
        
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $hashedPasswordFromDB = $row['password'];

        if (password_verify($password, $hashedPasswordFromDB)) {
            unset($row['password']);
            return json_encode(array("success" => $row));
        } else {
            return json_encode(["error" => "Invalid password"]);
        }
    }
    

}

$auth = new Auth();

if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {

        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'adopterSignup':
                echo $auth->adopterSignup($json);
                break;
            case 'adopterLogin':
                echo $auth->adopterLogin($json);
                break;
            case 'adminSignup':
                echo $auth->adminSignup($json);
                break;
            case 'adminLogin':
                echo $auth->adminLogin($json);
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