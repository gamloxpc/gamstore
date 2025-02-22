<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strip_tags(trim($_POST["name"]));
    $name = str_replace(array("\r","\n"),array(" "," "), $name);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $subject = strip_tags(trim($_POST["subject"]));
    $message = trim($_POST["message"]);

    // Vérifier les données
    if (empty($name) OR empty($message) OR !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Oops! Il y a eu un problème avec votre soumission. Veuillez compléter le formulaire et réessayer.";
        exit;
    }

    $recipient = "joakinschuh@gmail.com"; // Remplacez par votre adresse email
    $email_content = "Nom: $name\n";
    $email_content .= "Email: $email\n\n";
    $email_content .= "Message:\n$message\n";

    $email_headers = "De: $name <$email>";

    // Envoyer l'email
    if (mail($recipient, $subject, $email_content, $email_headers)) {
        http_response_code(200);
        echo "Merci! Votre message a été envoyé.";
    } else {
        http_response_code(500);
        echo "Oops! Un problème est survenu et nous n'avons pas pu envoyer votre message.";
    }

} else {
    http_response_code(403);
    echo "Il y a eu un problème avec votre soumission, veuillez réessayer.";
}
?>