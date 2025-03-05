<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Modal
    const modal = new bootstrap.Modal(document.getElementById("inputModal"), {
        backdrop: "static",
        keyboard: false,
    });

    modal.show();

    // OCR and Manual Input Handlers
    document.getElementById("ocr-button").addEventListener("click", function() {
        document.getElementById("membership-upload").click();
        modal.hide();
    });

    document.getElementById("manual-button").addEventListener("click", function() {
        modal.hide();
    });

    // File Input Change Event
    const fileInput = document.getElementById("membership-upload");
    const outputField = document.getElementById("ocr-output");
    const progressBar = document.getElementById("ocr-progress");

    fileInput.addEventListener("change", function(event) {
        const file = event.target.files[0];
        if (!file || !file.type.startsWith("image/")) {
            alert("Please upload a valid image file.");
            return;
        }

        outputField.value = "Processing... Please wait.";
        progressBar.style.width = "0%";
        progressBar.innerText = "";

        const formData = new FormData();
        formData.append("file", file);
        formData.append("apikey", "K86721170988957"); // Replace with your OCR.Space API key
        formData.append("language", "eng");
        formData.append("OCREngine", "2"); // Use OCR Engine 2 for better accuracy


        fetch("https://api.ocr.space/parse/image", {
                method: "POST",
                body: formData,
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.IsErroredOnProcessing) {
                    alert("OCR Error: " + data.ErrorMessage[0]);
                    outputField.value = "Error in OCR.";
                } else {
                    const text = data.ParsedResults[0].ParsedText;
                    console.log("OCR Text Extracted:", text);
                    outputField.value = formatOCRText(text); // Format the text
                    autofillForm(text); // Autofill the form
                }
            })
            .catch((error) => {
                console.error("OCR Error:", error);
                alert("An unexpected error occurred.");
                outputField.value = "Error in processing the image.";
            });
    });

    /**
     * Format OCR text into a structured output.
     * @param {string} text - Extracted OCR text
     * @returns {string} Formatted text
     */
    function formatOCRText(text) {
        let formattedText = text;

        // Example: Adjusting based on the structure of your form
        formattedText = formattedText.replace(/(PERSONAL INFORMATION)/g, "\n\n$1\n");
        formattedText = formattedText.replace(/(MEMBERSHIP APPLICATION FORM)/g, "\n\n$1\n");
        formattedText = formattedText.replace(/(\d+\.)/g, "\n\n$1 ");
        formattedText = formattedText.replace(/(Name:)/g, "\n$1 ");
        formattedText = formattedText.replace(/(Date of Birth:)/g, "\n$1 ");
        formattedText = formattedText.replace(/(Email Address:)/g, "\n$1 ");
        formattedText = formattedText.replace(/(Landline #:)/g, "\n$1 ");
        formattedText = formattedText.replace(/(Mobile Phone #:)/g, "\n$1 ");
        formattedText = formattedText.replace(/(KEY EXPERTISE:)/g, "\n\n$1\n");

        return formattedText;
    }

    /**
     * Autofill form fields based on OCR text.
     * @param {string} text - Extracted OCR text
     */
    function autofillForm(text) {
        const parseField = (regex) => {
            const match = text.match(regex);
            return match ? match[1].trim() : "";
        };

        try {
            // Personal Information Autofill
            document.querySelector('[name="name"]').value = parseField(/Name:\s*(.*?)\s*Date of Birth:/);
            document.querySelector('[name="dob"]').value = parseField(/Date of Birth:\s*(\d{2}\/\d{2}\/\d{4})/);
            document.querySelector('[name="sex"]').value = text.includes("Sex: Male") ? "Male" : "Female";
            document.querySelector('[name="current_address"]').value = parseField(
                /Current Address:\s*(.*?)\s*Permanent Address:/
            );
            document.querySelector('[name="permanent_address"]').value = parseField(
                /Permanent Address:\s*(.*?)\s*Email Address:/
            );
            document.querySelector('[name="email"]').value = parseField(/Email Address:\s*(.*?)\s*Landline/);
            document.querySelector('[name="landline"]').value = parseField(/Landline #:\s*(.*?)\s*Mobile/);
            document.querySelector('[name="mobile"]').value = parseField(
                /Mobile Phone #:\s*(.*?)\s*Place of Birth:/
            );

            console.log("OCR Autofill Complete.");
        } catch (error) {
            console.error("Autofill Error:", error);
            alert("Some fields could not be auto-filled. Please verify and complete the form manually.");
        }
    }
});
</script>