<?php
/* ============================================================
   iosss_app_process.php
   1) Receives and stores data submitted from iosss_application.html
   2) When accessed by an administrator,runs the selection process and lists the selected students
     for that school instead.
   ============================================================ */

/* ---------- 1. Database connection settings ----------*/
$db_host = "localhost";
$db_user = "root";
$db_pass = "@4171SAmy";
$db_name = "iosss_db2";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (mysqli_connect_errno()) {
echo "Databaseconnection failed:". mysqli_connect_error();
    exit();
}

define("MAX_SEATS", 10); // maximum candidates each school may admit

/* ============================================================
   ADMINISTRATOR MODE
   Reached via: iosss_app_process.php?admin=1&school=School+A
   ============================================================ */
if (isset($_GET['admin']) && $_GET['admin'] == '1') {

    // View every applicant who has ever submitted the form, regardless
    // of whether the selection process has placed them anywhere yet.
    if (isset($_GET['view']) && $_GET['view'] === 'all') {
        displayAllApplicants($conn);
        mysqli_close($conn);
        exit;
    }

    $school = isset($_GET['school']) ? clean($conn, $_GET['school']) : "";

    // Validate that the school actually exists
    $valid = mysqli_query($conn,
        "SELECT school_name FROM schools WHERE school_name = '$school'");
    if (!$valid || mysqli_num_rows($valid) === 0) {
        echo "<h2>Unknown school.</h2>";
        showSchoolMenu($conn);
        exit;
    }

    runSelection($conn);          // (re)compute the selection for ALL schools
    displaySelectedStudents($conn, $school);  // show this school's admitted list

    mysqli_close($conn);
    exit;
}

/* ============================================================
   FORM-SUBMISSION MODE (default) — requires POST
   ============================================================ */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid access. Please submit the application through the form, " .
        "or use ?admin=1&school=SchoolName to view selection results.");
}

/* ---------- 2. Helper function to clean input ---------- */
function clean($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value ?? ""));
}

/* ---------- 3. Receive candidate details ---------- */
$indexno = clean($conn, $_POST['indexno']);
$name    = clean($conn, $_POST['name']);
$sex     = clean($conn, $_POST['sex']);
$dob     = clean($conn, $_POST['dob']);
$pschool = clean($conn, $_POST['pschool']);

/* ---------- 4. Receive subject scores ---------- */
$english   = (int) clean($conn, $_POST['english']);
$kiswahili = (int) clean($conn, $_POST['kiswahili']);
$maths     = (int) clean($conn, $_POST['maths']);
$science   = (int) clean($conn, $_POST['science']);
$sst       = (int) clean($conn, $_POST['sst']);

/* ---------- 5. Receive school choices ---------- */
$choice1 = clean($conn, $_POST['choice1']);
$choice2 = clean($conn, $_POST['choice2']);
$choice3 = clean($conn, $_POST['choice3']);

/* ---------- 6. Basic server-side validation ---------- */
$errors = [];

if (!preg_match('/^\d{11}$/', $indexno)) {
    $errors[] = "Index number must be exactly 11 digits.";
}
if ($name === "") {
    $errors[] = "Name is required.";
}
if ($sex !== "Male" && $sex !== "Female") {
    $errors[] = "Sex must be specified.";
}
if ($dob === "") {
    $errors[] = "Date of birth is required.";
}
foreach (["english"=>$english,"kiswahili"=>$kiswahili,"maths"=>$maths,
          "science"=>$science,"sst"=>$sst] as $subject => $score) {
    if ($score < 0 || $score > 100) {
        $errors[] = ucfirst($subject) . " score must be between 0 and 100.";
    }
}
if ($choice1 === "" || $choice2 === "" || $choice3 === "") {
    $errors[] = "All three school choices must be selected.";
}
if ($choice1 === $choice2 || $choice1 === $choice3 || $choice2 === $choice3) {
    $errors[] = "The three school choices must all be different.";
}

// Every choice must be a real school that admits this candidate's sex
// (never trust the client-side JS alone for this).
if (empty($errors) && ($sex === "Male" || $sex === "Female")) {
    foreach (["choice1" => $choice1, "choice2" => $choice2, "choice3" => $choice3] as $label => $chosenSchool) {
        $check = mysqli_query($conn,
            "SELECT school_name FROM schools
             WHERE school_name = '" . mysqli_real_escape_string($conn, $chosenSchool) . "'
             AND gender = '" . mysqli_real_escape_string($conn, $sex) . "'");
        if (!$check || mysqli_num_rows($check) === 0) {
            $errors[] = "$label ('$chosenSchool') is not a valid " .
                        ($sex === "male" ? "boys'" : "girls'") . " school.";
        }
    }
}

if (!empty($errors)) {
    echo "<h2>Application not submitted — please correct the following:</h2><ul>";
    foreach ($errors as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul><p><a href='iosss_application.html'>Go back to the form</a></p>";
    mysqli_close($conn);
    exit;
}

/* ---------- 7. Compute the aggregate score ----------
   Aggregate = sum of the five subject scores (0 - 500).
   Adjust the formula here if your scheme uses a different method
   (e.g. total of best 4 subjects, or a mean grade points system). */
$aggregate = $english + $kiswahili + $maths + $science + $sst;

/* ---------- 8. Save the candidate's data to the database ---------- */
$sql = "INSERT INTO applicants
        (indexno, name, sex, dob, pschool,
         english, kiswahili, maths, science, sst, aggregate,
         choice1, choice2, choice3)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Statement preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "sssssiiiiiisss",
    $indexno, $name, $sex, $dob, $pschool,
    $english, $kiswahili, $maths, $science, $sst, $aggregate,
    $choice1, $choice2, $choice3
);

if (mysqli_stmt_execute($stmt)) {
    echo "<h2>Application Submitted Successfully</h2>";
    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>Index No.</th><td>" . htmlspecialchars($indexno) . "</td></tr>";
    echo "<tr><th>Name</th><td>" . htmlspecialchars($name) . "</td></tr>";
    echo "<tr><th>Sex</th><td>" . htmlspecialchars($sex) . "</td></tr>";
    echo "<tr><th>Date of Birth</th><td>" . htmlspecialchars($dob) . "</td></tr>";
    echo "<tr><th>Primary School</th><td>" . htmlspecialchars($pschool) . "</td></tr>";
    echo "<tr><th>English</th><td>$english</td></tr>";
    echo "<tr><th>Kiswahili</th><td>$kiswahili</td></tr>";
    echo "<tr><th>Mathematics</th><td>$maths</td></tr>";
    echo "<tr><th>Science</th><td>$science</td></tr>";
    echo "<tr><th>SST</th><td>$sst</td></tr>";
    echo "<tr><th>Aggregate</th><td><strong>$aggregate</strong></td></tr>";
    echo "<tr><th>1st Choice</th><td>" . htmlspecialchars($choice1) . "</td></tr>";
    echo "<tr><th>2nd Choice</th><td>" . htmlspecialchars($choice2) . "</td></tr>";
    echo "<tr><th>3rd Choice</th><td>" . htmlspecialchars($choice3) . "</td></tr>";
    echo "</table>";
} else {
    echo "<p>Error saving application: " . mysqli_error($conn) . "</p>";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);


/* ================================================================
   SELECTION FUNCTIONS
   (Defined here so they are available to the admin block above —
   PHP loads all function definitions in the file before running it.)
   ================================================================ */

/**
 * runSelection()
 * Places every applicant into at most one school, following the
 * two selection rules:
 *   1. Higher aggregate is ranked first.
 *   2. A candidate's 1st choice is honoured before their 2nd,
 *      which is honoured before their 3rd.
 * A candidate can never end up selected by more than one school,
 * because once placed they are excluded from every later round.
 */
function runSelection($conn) {

    // Start from a clean slate each time this is run
    mysqli_query($conn, "UPDATE applicants SET selected_school = NULL, choice_number = NULL");

    $schools = [];
    $res = mysqli_query($conn, "SELECT school_name, capacity FROM schools");
    while ($row = mysqli_fetch_assoc($res)) {
        $schools[$row['school_name']] = (int) $row['capacity'];
    }

    // Round 1 = 1st choice, Round 2 = 2nd choice, Round 3 = 3rd choice
    for ($round = 1; $round <= 3; $round++) {
        $choiceCol = "choice{$round}";

        foreach ($schools as $schoolName => $capacity) {

            // How many seats are already filled at this school?
            $countRes = mysqli_query($conn,
                "SELECT COUNT(*) AS taken FROM applicants
                 WHERE selected_school = '" . mysqli_real_escape_string($conn, $schoolName) . "'");
            $taken = (int) mysqli_fetch_assoc($countRes)['taken'];
            $vacancies = $capacity - $taken;

            if ($vacancies <= 0) {
                continue; // school is already full
            }

            // Candidates who put this school as their choice for this round,
            // are not yet placed anywhere, ranked by aggregate (best first)
            $sql = "SELECT indexno, aggregate FROM applicants
                    WHERE {$choiceCol} = '" . mysqli_real_escape_string($conn, $schoolName) . "'
                    AND selected_school IS NULL
                    ORDER BY aggregate DESC
                    LIMIT {$vacancies}";
            $candidates = mysqli_query($conn, $sql);

            while ($cand = mysqli_fetch_assoc($candidates)) {
                $idx = mysqli_real_escape_string($conn, $cand['indexno']);
                mysqli_query($conn,
                    "UPDATE applicants
                     SET selected_school = '" . mysqli_real_escape_string($conn, $schoolName) . "',
                         choice_number = {$round}
                     WHERE indexno = '{$idx}'");
            }
        }
    }
}

/**
 * displaySelectedStudents()
 * Lists (up to MAX_SEATS) students admitted to the given school:
 * Index Number, Name, Aggregate, and which choice (1/2/3) it was.
 */
function displaySelectedStudents($conn, $schoolName) {
    $safeSchool = mysqli_real_escape_string($conn, $schoolName);

    $sql = "SELECT indexno, name, aggregate, choice_number
            FROM applicants
            WHERE selected_school = '{$safeSchool}'
            ORDER BY aggregate DESC";
    $result = mysqli_query($conn, $sql);

    echo "<h2>Selected Students — " . htmlspecialchars($schoolName) . "</h2>";
    echo "<p>Maximum capacity: " . MAX_SEATS . "</p>";

    if (mysqli_num_rows($result) === 0) {
        echo "<p>No students have been selected for this school.</p>";
        return;
    }

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>#</th><th>Index Number</th><th>Name</th>"
       . "<th>Aggregate</th><th>Choice Given</th></tr>";

    $i = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $ordinal = [1 => "1st", 2 => "2nd", 3 => "3rd"][$row['choice_number']];
        echo "<tr>"
           . "<td>{$i}</td>"
           . "<td>" . htmlspecialchars($row['indexno']) . "</td>"
           . "<td>" . htmlspecialchars($row['name']) . "</td>"
           . "<td>{$row['aggregate']}</td>"
           . "<td>{$ordinal}</td>"
           . "</tr>";
        $i++;
    }
    echo "</table>";
}

/**
 * showSchoolMenu()
 * Small convenience page letting an administrator pick which
 * school's selected list to view.
 */
/**
 * displayAllApplicants()
 * Lists every applicant who has submitted the form so far, with their
 * bio-data, scores, aggregate, choices, and current selection status
 * (whether or not runSelection() has placed them anywhere yet).
 */
function displayAllApplicants($conn) {
    $sql = "SELECT indexno, name, sex, dob, pschool,
                   english, kiswahili, maths, science, sst, aggregate,
                   choice1, choice2, choice3, selected_school, choice_number
            FROM applicants
            ORDER BY aggregate DESC";
    $result = mysqli_query($conn, $sql);

    echo "<h2>All Submitted Applicants</h2>";

    if (mysqli_num_rows($result) === 0) {
        echo "<p>No applicants have submitted the form yet.</p>";
        return;
    }

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>"
       . "<th>Index No.</th><th>Name</th><th>Sex</th><th>DOB</th><th>Primary School</th>"
       . "<th>English</th><th>Kiswahili</th><th>Maths</th><th>Science</th><th>SST</th>"
       . "<th>Aggregate</th>"
       . "<th>1st Choice</th><th>2nd Choice</th><th>3rd Choice</th>"
       . "<th>Selected School</th><th>Choice Used</th>"
       . "</tr>";

    $ordinals = [1 => "1st", 2 => "2nd", 3 => "3rd"];

    while ($row = mysqli_fetch_assoc($result)) {
        $status = $row['selected_school']
            ? htmlspecialchars($row['selected_school'])
            : "Not yet selected";
        $choiceUsed = $row['choice_number'] ? $ordinals[$row['choice_number']] : "-";

        echo "<tr>"
           . "<td>" . htmlspecialchars($row['indexno']) . "</td>"
           . "<td>" . htmlspecialchars($row['name']) . "</td>"
           . "<td>" . htmlspecialchars($row['sex']) . "</td>"
           . "<td>" . htmlspecialchars($row['dob']) . "</td>"
           . "<td>" . htmlspecialchars($row['pschool']) . "</td>"
           . "<td>{$row['english']}</td>"
           . "<td>{$row['kiswahili']}</td>"
           . "<td>{$row['maths']}</td>"
           . "<td>{$row['science']}</td>"
           . "<td>{$row['sst']}</td>"
           . "<td><strong>{$row['aggregate']}</strong></td>"
           . "<td>" . htmlspecialchars($row['choice1']) . "</td>"
           . "<td>" . htmlspecialchars($row['choice2']) . "</td>"
           . "<td>" . htmlspecialchars($row['choice3']) . "</td>"
           . "<td>{$status}</td>"
           . "<td>{$choiceUsed}</td>"
           . "</tr>";
    }
    echo "</table>";
    echo "<p><em>Note: \"Selected School\" only reflects the outcome of the last time "
       . "the selection process was run. Visit a specific school's admin link to "
       . "trigger a fresh run.</em></p>";
}

function showSchoolMenu($conn) {
    echo "<h2>View Selected Students</h2>";
    foreach (["Male" => "Boys' Schools", "Female" => "Girls' Schools"] as $gender => $heading) {
        echo "<h3>{$heading}</h3><ul>";
        $safeGender = mysqli_real_escape_string($conn, $gender);
        $res = mysqli_query($conn,
            "SELECT school_name FROM schools WHERE gender = '{$safeGender}' ORDER BY school_name");
        while ($row = mysqli_fetch_assoc($res)) {
            $name = htmlspecialchars($row['school_name']);
            $encoded = urlencode($row['school_name']);
            echo "<li><a href='iosss_app_process.php?admin=1&school={$encoded}'>{$name}</a></li>";
        }
        echo "</ul>";
    }
}
?>