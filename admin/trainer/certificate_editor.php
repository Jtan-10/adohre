<?php
// certificate_editor.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../admin_header.php';
require_once '../../backend/db/db_connect.php';

// Check if user is a trainer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: /capstone-php/index.php');
    exit();
}

if (!isset($_GET['training_id'])) {
    echo "Training ID not provided.";
    exit();
}

$training_id = intval($_GET['training_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Certificate Editor - ADOHRE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* Container with a fixed aspect ratio for A4 landscape (1123x792 approx). */
    #canvas-container {
        width: 100%;
        max-width: 1123px;
        aspect-ratio: 1123 / 792;
        margin: 20px auto;
        position: relative;
        border: 1px solid #ccc;
    }

    /* The canvas fills the container. */
    #certificateCanvas {
        width: 100%;
        height: auto;
        display: block;
    }

    /* Design selector (horizontal slideshow) */
    #designSelector {
        display: flex;
        overflow-x: auto;
        gap: 10px;
        margin-bottom: 20px;
    }

    #designSelector img {
        width: 150px;
        cursor: pointer;
        border: 2px solid transparent;
    }

    #designSelector img.selected {
        border-color: #28a745;
    }
    </style>
    <!-- Fabric.js -->
    <script src="https://cdn.jsdelivr.net/npm/fabric@4.6.0/dist/fabric.min.js"></script>
</head>

<body>
    <div class="container mt-4">
        <h1>Certificate Editor</h1>
        <p>Design your certificate for Training ID: <?php echo htmlspecialchars($training_id); ?></p>

        <!-- Design Selector: Horizontal slideshow of pre-made certificate designs -->
        <div id="designSelector">
            <img src="../../assets/design1.png" alt="Design 1">
            <img src="../../assets/design2.png" alt="Design 2">
            <img src="../../assets/design3.png" alt="Design 3">
        </div>

        <form id="certificateForm">
            <input type="hidden" id="selectedDesign" name="selected_design" value="">
            <input type="hidden" name="training_id" value="<?php echo htmlspecialchars($training_id); ?>">

            <!-- Upload a certificate template (optional override) -->
            <div class="mb-3">
                <label for="bgImage" class="form-label">Upload Certificate Template (A4 Landscape recommended)</label>
                <input type="file" id="bgImage" name="certificate_background" accept="image/*" class="form-control">
            </div>

            <!-- Canvas Container (Letter Landscape Ratio) -->
            <div id="canvas-container">
                <canvas id="certificateCanvas"></canvas>
            </div>

            <!-- Editor Controls -->
            <div class="row mt-3">
                <div class="col-md-3 mb-3">
                    <button type="button" id="addTextBtn" class="btn btn-secondary w-100">Add Text</button>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="clearCanvasBtn" class="btn btn-warning w-100">Clear Canvas</button>
                </div>
                <!-- Font style control -->
                <div class="col-md-3 mb-3">
                    <label for="fontFamilySelect" class="form-label">Font Style</label>
                    <select id="fontFamilySelect" class="form-select">
                        <option value="Arial" selected>Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Verdana">Verdana</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="saveLayoutBtn" class="btn btn-success w-100">Save Layout</button>
                </div>
            </div>

            <!-- Additional Controls for lines, images, text color, line thickness, and deletion -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="lineColor" class="form-label">Line Color</label>
                    <input type="color" id="lineColor" class="form-control" value="#000000">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="addLineBtn" class="btn btn-primary w-100">Add Line</button>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="textColorPicker" class="form-label">Text Color</label>
                    <input type="color" id="textColorPicker" class="form-control" value="#000000">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="changeTextColorBtn" class="btn btn-secondary w-100">Change Text
                        Color</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="lineThickness" class="form-label">Line Thickness</label>
                    <input type="number" id="lineThickness" class="form-control" value="2" min="1" max="20">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="changeLineThicknessBtn" class="btn btn-primary w-100">Set Line
                        Thickness</button>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" id="deleteObjectBtn" class="btn btn-danger w-100">Delete Object</button>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="addImageInput" class="form-label">Add Another Image</label>
                    <input type="file" id="addImageInput" name="add_image" accept="image/*" class="form-control">
                </div>
            </div>

            <a href="assessments.php" class="btn btn-info">Back to Assessments</a>
        </form>
    </div>

    <script>
    /***********************************************************
     * 1. Initialize Fabric canvas with default A4 landscape dimensions.
     ***********************************************************/
    const BASE_URL = "http://localhost/capstone-php/"; // Adjust if needed
    const TRAINING_ID = <?php echo $training_id; ?>;
    const DEFAULT_WIDTH = 1123;
    const DEFAULT_HEIGHT = 792;
    const canvas = new fabric.Canvas('certificateCanvas', {
        width: DEFAULT_WIDTH,
        height: DEFAULT_HEIGHT
    });

    // Set container dimensions
    const container = document.getElementById('canvas-container');
    container.style.width = DEFAULT_WIDTH + 'px';
    container.style.height = DEFAULT_HEIGHT + 'px';

    /***********************************************************
     * Utility: Title Case
     ***********************************************************/
    function toTitleCase(str) {
        return str.replace(/\w\S*/g, function(txt) {
            return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
        });
    }

    /***********************************************************
     * 2. Create default placeholders
     ***********************************************************/
    const userName = "<?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Your Name'; ?>";
    const titleCaseUserName = toTitleCase(userName);

    const placeholders = [];

    const certificateTitle = new fabric.IText('CERTIFICATE OF APPRECIATION', {
        left: 320,
        top: 60,
        fontFamily: 'Georgia',
        fill: '#000',
        fontSize: 40,
        selectable: true
    });
    placeholders.push(certificateTitle);

    const presentedToText = new fabric.IText('THIS CERTIFICATE IS PROUDLY PRESENTED TO', {
        left: 250,
        top: 120,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 24,
        selectable: true
    });
    placeholders.push(presentedToText);

    const trainingTitleText = new fabric.IText('[Training Title]', {
        left: 320,
        top: 170,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 28,
        selectable: true
    });
    placeholders.push(trainingTitleText);

    const namePlaceholderText = new fabric.IText('[Name]', {
        left: 360,
        top: 220,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 36,
        selectable: true
    });
    placeholders.push(namePlaceholderText);

    const loremLineText = new fabric.IText(
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit,\nsed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', {
            left: 180,
            top: 280,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 18,
            selectable: true,
            textAlign: 'center'
        }
    );
    placeholders.push(loremLineText);

    const datePlaceholderText = new fabric.IText('[Date]', {
        left: 220,
        top: 420,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 24,
        selectable: true
    });
    placeholders.push(datePlaceholderText);



    // Add placeholders to canvas
    placeholders.forEach(obj => canvas.add(obj));

    /***********************************************************
     * 3. Clamp function to ensure objects remain in bounds.
     ***********************************************************/
    function clampToBounds(obj, newWidth, newHeight) {
        const scaledW = obj.getScaledWidth();
        const scaledH = obj.getScaledHeight();
        if (obj.left < 0) obj.left = 0;
        if (obj.left + scaledW > newWidth) obj.left = newWidth - scaledW;
        if (obj.top < 0) obj.top = 0;
        if (obj.top + scaledH > newHeight) obj.top = newHeight - scaledH;
    }

    /***********************************************************
     * 4. Background upload: force scale to A4 landscape.
     ***********************************************************/
    document.getElementById('bgImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            const data = evt.target.result;
            fabric.Image.fromURL(data, function(img) {
                const ratio = img.width / img.height;
                const targetRatio = DEFAULT_WIDTH / DEFAULT_HEIGHT;
                const tolerance = 0.1;
                if (Math.abs(ratio - targetRatio) / targetRatio > tolerance) {
                    alert(
                        "Warning: The uploaded image is not A4 landscape. It will be stretched to fit A4."
                    );
                }
                img.scaleX = DEFAULT_WIDTH / img.width;
                img.scaleY = DEFAULT_HEIGHT / img.height;

                canvas.setWidth(DEFAULT_WIDTH);
                canvas.setHeight(DEFAULT_HEIGHT);
                container.style.width = DEFAULT_WIDTH + 'px';
                container.style.height = DEFAULT_HEIGHT + 'px';

                canvas.setBackgroundImage(img, () => {
                    placeholders.forEach(ph => {
                        clampToBounds(ph, DEFAULT_WIDTH, DEFAULT_HEIGHT);
                        canvas.bringToFront(ph);
                    });
                    canvas.renderAll();
                }, {
                    originX: 'left',
                    originY: 'top'
                });
            });
        };
        reader.readAsDataURL(file);
    });

    /***********************************************************
     * 5. Design Selector: load pre-made designs.
     ***********************************************************/
    document.querySelectorAll('#designSelector img').forEach(thumb => {
        thumb.addEventListener('click', function() {
            // Remove "selected" class from all, then add it to the clicked thumbnail.
            document.querySelectorAll('#designSelector img').forEach(img => img.classList.remove(
                'selected'));
            this.classList.add('selected');

            // Get the design URL from the image source.
            const bgUrl = this.src;
            // Update the hidden input so that the selected design is saved.
            document.getElementById('selectedDesign').value = bgUrl;

            // Load the image from the URL and set it as the canvas background.
            fabric.Image.fromURL(bgUrl, function(img) {
                img.scaleX = DEFAULT_WIDTH / img.width;
                img.scaleY = DEFAULT_HEIGHT / img.height;
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                    originX: 'left',
                    originY: 'top'
                });
            });
        });
    });




    /***********************************************************
     * 6. Add Text: create a new text object.
     ***********************************************************/
    document.getElementById('addTextBtn').addEventListener('click', () => {
        const textObj = new fabric.IText('Your Text Here', {
            left: 100,
            top: 100,
            fontFamily: 'Arial',
            fill: '#000',
            fontSize: 36,
            selectable: true
        });
        canvas.add(textObj);
    });

    /***********************************************************
     * 7. Clear Canvas (except background)
     ***********************************************************/
    document.getElementById('clearCanvasBtn').addEventListener('click', () => {
        const bg = canvas.backgroundImage;
        canvas.clear();
        if (bg) {
            canvas.setBackgroundImage(bg, canvas.renderAll.bind(canvas));
        }
        placeholders.forEach(obj => canvas.add(obj));
        canvas.renderAll();
    });

    /***********************************************************
     * 8. Font Style Control
     ***********************************************************/
    document.getElementById('fontFamilySelect').addEventListener('change', function() {
        const newFont = this.value;
        const activeObj = canvas.getActiveObject();
        if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'textbox')) {
            activeObj.set({
                fontFamily: newFont
            });
            canvas.renderAll();
        }
    });

    /***********************************************************
     * 9. Save Layout: export canvas JSON and send to backend.
     ***********************************************************/
    document.getElementById('saveLayoutBtn').addEventListener('click', () => {
        // Use toDatalessJSON to avoid embedding full data URLs (if possible)
        const layoutJSON = JSON.stringify(canvas.toDatalessJSON());
        const formData = new FormData(document.getElementById('certificateForm'));

        // Remove the extra file input from FormData so that its data isn't sent.
        formData.delete('add_image'); // Make sure the input has name="add_image"

        formData.append('layout_json', layoutJSON);
        formData.append('action', 'save_certificate_layout');

        // Also append the final canvas image data
        const finalImageData = canvas.toDataURL({
            format: 'png',
            quality: 1.0
        });
        formData.append('final_image', finalImageData);

        fetch('../../backend/models/generate_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.text())
            .then(text => {
                console.log("Raw response:", text);
                try {
                    const data = JSON.parse(text);
                    if (data.status) {
                        alert('Certificate layout saved successfully.');
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (err) {
                    console.error("JSON parse error:", err);
                    alert("Error parsing server response.");
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to save certificate layout.');
            });
    });




    /***********************************************************
     * 10. Add Line: create a horizontal line with selected color.
     ***********************************************************/
    document.getElementById('addLineBtn').addEventListener('click', () => {
        const lineColor = document.getElementById('lineColor').value;
        const line = new fabric.Line([50, 50, 300, 50], {
            stroke: lineColor,
            strokeWidth: 2,
            selectable: true
        });
        canvas.add(line);
    });

    /***********************************************************
     * 11. Add Another Image: insert an additional image onto the canvas.
     ***********************************************************/
    document.getElementById('addImageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            fabric.Image.fromURL(evt.target.result, function(img) {
                img.set({
                    left: canvas.width / 2 - img.width / 2,
                    top: canvas.height / 2 - img.height / 2
                });
                canvas.add(img);
                canvas.renderAll();
            });
        };
        reader.readAsDataURL(file);
    });

    /***********************************************************
     * 12. Font Color & Line Thickness Controls
     ***********************************************************/
    // 12a. Text Color
    const textColorPicker = document.getElementById('textColorPicker');
    const changeTextColorBtn = document.getElementById('changeTextColorBtn');
    changeTextColorBtn.addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'textbox')) {
            activeObj.set({
                fill: textColorPicker.value
            });
            canvas.renderAll();
        } else {
            alert("Please select a text object first.");
        }
    });

    // 12b. Line Thickness
    const lineThicknessInput = document.getElementById('lineThickness');
    const changeLineThicknessBtn = document.getElementById('changeLineThicknessBtn');
    changeLineThicknessBtn.addEventListener('click', () => {
        const thickness = parseFloat(lineThicknessInput.value);
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type === 'line') {
            activeObj.set({
                strokeWidth: thickness
            });
            canvas.renderAll();
        } else {
            alert("Please select a line object first.");
        }
    });

    /***********************************************************
     * 13. Delete Object: remove the currently selected object.
     ***********************************************************/
    document.getElementById('deleteObjectBtn').addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            canvas.remove(activeObj);
            canvas.renderAll();
        } else {
            alert("Please select an object to delete.");
        }
    });

    // Function to load saved layout from the backend.
    function loadSavedLayout() {
        fetch(`../../backend/models/generate_certificate.php?action=load_certificate_layout&training_id=${TRAINING_ID}`)
            .then(response => response.json())
            .then(data => {
                if (data.status && data.data && data.data.layout_json) {
                    // Load the saved layout JSON into the canvas.
                    canvas.loadFromJSON(data.data.layout_json, () => {
                        // If a background image is saved, load it.
                        if (data.data.background_image && data.data.background_image !== '') {
                            const bgUrl = BASE_URL + data.data.background_image;
                            fabric.Image.fromURL(bgUrl, function(img) {
                                img.scaleX = DEFAULT_WIDTH / img.width;
                                img.scaleY = DEFAULT_HEIGHT / img.height;
                                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                                    originX: 'left',
                                    originY: 'top'
                                });
                            });
                        }
                        canvas.renderAll();
                    });
                } else {
                    console.log("No saved layout found.");
                }
            })
            .catch(err => {
                console.error("Error loading saved layout:", err);
            });
    }


    // Call the function on page load.
    window.addEventListener('load', loadSavedLayout);
    </script>
</body>

</html>