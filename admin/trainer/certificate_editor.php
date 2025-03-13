<?php
// certificate_editor.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../admin_header.php';
require_once '../../backend/db/db_connect.php';

// Ensure the user is a trainer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: /capstone-php/index.php');
    exit();
}

if (!isset($_GET['training_id'])) {
    echo "Training ID not provided.";
    exit();
}

$training_id = intval($_GET['training_id']);

// (Optional) fetch the training title from the DB.
// For simplicity we use a placeholder. You may query the `trainings` table.
$training_title = "Training Title";

// Fetch participants for this training
$participants = [];
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name
    FROM training_registrations tr
    JOIN users u ON tr.user_id = u.user_id
    WHERE tr.training_id = ?
    ORDER BY u.first_name, u.last_name
");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $participants[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Editor - ADOHRE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* Container for the Fabric canvas (A4 landscape: 1123px x 792px) */
    #canvas-container {
        width: 100%;
        max-width: 1123px;
        aspect-ratio: 1123 / 792;
        margin: 20px auto;
        position: relative;
        border: 1px solid #ccc;
    }

    #certificateCanvas {
        width: 100%;
        height: auto;
        display: block;
    }

    /* Design Selector */
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

    /* Preview Modal */
    #pdfPreviewModal {
        display: none;
        position: fixed;
        top: 5%;
        left: 5%;
        width: 90%;
        height: 90%;
        background-color: #fff;
        border: 1px solid #ccc;
        z-index: 9999;
        padding: 10px;
    }

    #pdfPreviewFrame {
        width: 100%;
        height: 100%;
        border: none;
    }

    #closePreviewBtn {
        position: absolute;
        top: 5px;
        right: 5px;
    }
    </style>
    <!-- Fabric.js -->
    <script src="https://cdn.jsdelivr.net/npm/fabric@4.6.0/dist/fabric.min.js"></script>
</head>

<body>
    <div class="container mt-4">
        <h1>Certificate Editor</h1>
        <p>
            Designing certificate for Training ID: <strong><?php echo htmlspecialchars($training_id); ?></strong>
            (<strong><?php echo htmlspecialchars($training_title); ?></strong>)<br>
            Date: <strong><?php echo date("Y-m-d"); ?></strong>
        </p>
        <!-- Design Selector (pre-made backgrounds) -->
        <div id="designSelector">
            <img src="../../assets/design1.png" alt="Design 1">
            <img src="../../assets/design2.png" alt="Design 2">
            <img src="../../assets/design3.png" alt="Design 3">
        </div>
        <form id="certificateForm">
            <input type="hidden" id="selectedDesign" name="selected_design" value="">
            <input type="hidden" name="training_id" value="<?php echo htmlspecialchars($training_id); ?>">
            <!-- Background image upload -->
            <div class="mb-3">
                <label for="bgImage" class="form-label">Upload Certificate Template (A4 Landscape recommended)</label>
                <input type="file" id="bgImage" name="certificate_background" accept="image/*" class="form-control">
            </div>
            <!-- Canvas Container -->
            <div id="canvas-container">
                <canvas id="certificateCanvas"></canvas>
            </div>
            <!-- Editor Controls -->
            <div class="row mt-3">
                <div class="col-md-2 mb-3">
                    <button type="button" id="addTextBtn" class="btn btn-secondary w-100">Add Text</button>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="clearCanvasBtn" class="btn btn-warning w-100">Clear Canvas</button>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="fontFamilySelect" class="form-label">Font Style</label>
                    <select id="fontFamilySelect" class="form-select">
                        <option value="Arial" selected>Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Verdana">Verdana</option>
                    </select>
                </div>
                <!-- Navigation buttons -->
                <div class="col-md-2 mb-3">
                    <button type="button" id="prevCertBtn" class="btn btn-info w-100">Previous</button>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="button" id="nextCertBtn" class="btn btn-info w-100">Next</button>
                </div>
                <!-- Save Layout for current participant -->
                <div class="col-md-2 mb-3">
                    <button type="button" id="saveThisLayoutBtn" class="btn btn-success w-100">Save This Layout</button>
                </div>
            </div>
            <!-- Additional Controls -->
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
            <!-- Preview Section -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <label for="previewUserId" class="form-label">Preview As (Participant)</label>
                    <select id="previewUserId" name="preview_user_id" class="form-select">
                        <?php foreach ($participants as $p): ?>
                        <option value="<?php echo $p['user_id']; ?>">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="previewPdfBtn" class="btn btn-info w-100 mt-4">Preview PDF</button>
                </div>
                <div class="col-md-3">
                    <button type="button" id="saveAllLayoutsBtn" class="btn btn-success w-100 mt-4">Save All
                        Layouts</button>
                </div>
            </div>
            <a href="assessments.php" class="btn btn-info mt-3">Back to Assessments</a>
        </form>
    </div>

    <!-- Hidden PDF Preview Modal -->
    <div id="pdfPreviewModal">
        <button id="closePreviewBtn" class="btn btn-danger">Close</button>
        <iframe id="pdfPreviewFrame"></iframe>
    </div>

    <script>
    /***********************************************************
     * 1. Initialize Fabric canvas
     ***********************************************************/
    const TRAINING_ID = <?php echo $training_id; ?>;
    const DEFAULT_WIDTH = 1123;
    const DEFAULT_HEIGHT = 792;
    const canvas = new fabric.Canvas('certificateCanvas', {
        width: DEFAULT_WIDTH,
        height: DEFAULT_HEIGHT
    });
    const container = document.getElementById('canvas-container');
    container.style.width = DEFAULT_WIDTH + 'px';
    container.style.height = DEFAULT_HEIGHT + 'px';

    /***********************************************************
     * 2. Set up default placeholders
     ***********************************************************/
    const trainingTitlePlaceholder = new fabric.IText('[Training Title]', {
        left: canvas.width / 2,
        top: 50,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 28,
        originX: 'center',
        selectable: true
    });
    canvas.add(trainingTitlePlaceholder);

    const datePlaceholder = new fabric.IText('[Date]', {
        left: 50,
        top: canvas.height - 70,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 24,
        selectable: true
    });
    // Auto-populate with current date
    datePlaceholder.text = new Date().toISOString().split('T')[0];
    canvas.add(datePlaceholder);

    const namePlaceholder = new fabric.IText('[Name]', {
        left: canvas.width / 2,
        top: 220,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 36,
        originX: 'center',
        textAlign: 'center',
        selectable: true,
        placeholderType: 'name' // Custom property added
    });
    canvas.add(namePlaceholder);

    const bodyPlaceholder = new fabric.IText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', {
        left: canvas.width / 2,
        top: 300,
        fontFamily: 'Arial',
        fill: '#000',
        fontSize: 18,
        originX: 'center',
        textAlign: 'center',
        selectable: true
    });
    canvas.add(bodyPlaceholder);

    /***********************************************************
     * 3. Update Name Placeholder based on Participant selection
     ***********************************************************/
    function updateNamePlaceholder() {
        const select = document.getElementById('previewUserId');
        if (!select || select.selectedIndex < 0) return; // Safety check

        const selectedText = select.options[select.selectedIndex].text;
        console.log("Updating name placeholder to:", selectedText);

        // Find name placeholders in the canvas
        let namePlaceholders = [];
        let foundPlaceholder = false;

        // Method 1: Find by placeholderType property
        canvas.getObjects('i-text').forEach(function(obj) {
            if (obj.placeholderType === 'name') {
                console.log("Found placeholder by placeholderType:", obj);
                namePlaceholders.push(obj);
                foundPlaceholder = true;
            }
        });

        // Method 2: Find by text content or matching criteria
        if (!foundPlaceholder) {
            canvas.getObjects('i-text').forEach(function(obj) {
                if (obj.text && (
                        obj.text === '[Name]' ||
                        obj.text.includes('[Name]') ||
                        obj.originalText === '[Name]'
                    )) {
                    console.log("Found placeholder by text content:", obj);
                    obj.placeholderType = 'name'; // Mark for future
                    obj.originalText = obj.originalText || obj.text;
                    namePlaceholders.push(obj);
                    foundPlaceholder = true;
                }
            });
        }

        // Method 3: Find by special positioning and size
        if (!foundPlaceholder) {
            // Look for centered text positioned in the middle third of the certificate
            canvas.getObjects('i-text').forEach(function(obj) {
                // Check if it's text that's centered (originX=center) and positioned in the middle
                if (obj.originX === 'center' &&
                    obj.top > canvas.height * 0.25 &&
                    obj.top < canvas.height * 0.75) {
                    console.log("Found potential name placeholder by position:", obj);
                    obj.placeholderType = 'name';
                    obj.originalText = obj.originalText || obj.text;
                    namePlaceholders.push(obj);
                    foundPlaceholder = true;
                }
            });
        }

        // Method 4: If still not found, consider the largest text objects
        if (!foundPlaceholder) {
            console.log("Searching by font size as last resort");
            let largestFontObj = null;
            let largestFont = 0;
            canvas.getObjects('i-text').forEach(function(obj) {
                if (obj.fontSize > largestFont) {
                    largestFont = obj.fontSize;
                    largestFontObj = obj;
                }
            });
            if (largestFontObj) {
                console.log("Using largest text as name placeholder:", largestFontObj);
                largestFontObj.placeholderType = 'name';
                largestFontObj.originalText = largestFontObj.originalText || largestFontObj.text;
                namePlaceholders.push(largestFontObj);
                foundPlaceholder = true;
            }
        }

        // Update all found placeholders
        if (namePlaceholders.length > 0) {
            namePlaceholders.forEach(function(obj) {
                // Force update the text regardless of what was loaded from JSON
                obj.set('text', selectedText);
            });
            canvas.renderAll();
            console.log("Updated", namePlaceholders.length, "name placeholder(s) to:", selectedText);
        } else {
            console.warn("⚠️ No name placeholder found in the canvas!");
        }
    }

    /***********************************************************
     * 4. Navigation: Cycle through participants using Prev/Next buttons
     ***********************************************************/
    function getCurrentParticipantIndex() {
        const select = document.getElementById('previewUserId');
        return select.selectedIndex;
    }

    function setParticipantByIndex(index) {
        const select = document.getElementById('previewUserId');
        if (index >= 0 && index < select.options.length) {
            select.selectedIndex = index;
            updateNamePlaceholder();
        }
    }
    document.getElementById('prevCertBtn').addEventListener('click', () => {
        let idx = getCurrentParticipantIndex();
        setParticipantByIndex(idx - 1);
    });
    document.getElementById('nextCertBtn').addEventListener('click', () => {
        let idx = getCurrentParticipantIndex();
        setParticipantByIndex(idx + 1);
    });

    /***********************************************************
     * 5. Background Upload & Design Selector
     ***********************************************************/
    document.getElementById('bgImage').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            fabric.Image.fromURL(evt.target.result, function(img) {
                const ratio = img.width / img.height;
                const targetRatio = DEFAULT_WIDTH / DEFAULT_HEIGHT;
                if (Math.abs(ratio - targetRatio) / targetRatio > 0.1) {
                    alert("Warning: The uploaded image is not A4 landscape. It will be stretched.");
                }
                img.scaleX = DEFAULT_WIDTH / img.width;
                img.scaleY = DEFAULT_HEIGHT / img.height;
                canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), {
                    originX: 'left',
                    originY: 'top'
                });
            });
        };
        reader.readAsDataURL(file);
    });
    document.querySelectorAll('#designSelector img').forEach(thumb => {
        thumb.addEventListener('click', function() {
            document.querySelectorAll('#designSelector img').forEach(img => img.classList.remove(
                'selected'));
            this.classList.add('selected');
            const bgUrl = this.src;
            document.getElementById('selectedDesign').value = bgUrl;
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
     * 6. Additional Controls
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
    document.getElementById('clearCanvasBtn').addEventListener('click', () => {
        const bg = canvas.backgroundImage;
        canvas.clear();
        if (bg) {
            canvas.setBackgroundImage(bg, canvas.renderAll.bind(canvas));
        }
        // Re-add default placeholders
        canvas.add(trainingTitlePlaceholder, datePlaceholder, namePlaceholder, bodyPlaceholder);
        canvas.renderAll();
    });
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
    document.getElementById('addLineBtn').addEventListener('click', () => {
        const lineColor = document.getElementById('lineColor').value;
        const line = new fabric.Line([50, 50, 300, 50], {
            stroke: lineColor,
            strokeWidth: 2,
            selectable: true
        });
        canvas.add(line);
    });
    document.getElementById('changeTextColorBtn').addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && (activeObj.type === 'i-text' || activeObj.type === 'textbox')) {
            activeObj.set({
                fill: document.getElementById('textColorPicker').value
            });
            canvas.renderAll();
        } else {
            alert("Please select a text object first.");
        }
    });
    document.getElementById('changeLineThicknessBtn').addEventListener('click', () => {
        const thickness = parseFloat(document.getElementById('lineThickness').value);
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
    document.getElementById('deleteObjectBtn').addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            canvas.remove(activeObj);
            canvas.renderAll();
        } else {
            alert("Please select an object to delete.");
        }
    });
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
     * 7. Save This Layout (for current participant)
     *    Save both the final PNG image and the canvas JSON
     ***********************************************************/
    document.getElementById('saveThisLayoutBtn').addEventListener('click', () => {
        const previewUserId = document.getElementById('previewUserId').value;
        if (!previewUserId) {
            alert("No participant selected.");
            return;
        }
        const layoutImage = canvas.toDataURL({
            format: 'png',
            quality: 1.0
        });
        const canvasJSON = JSON.stringify(canvas.toJSON());
        const formData = new FormData(document.getElementById('certificateForm'));
        formData.delete('add_image');
        formData.append('final_image', layoutImage);
        formData.append('canvas_json', canvasJSON);
        formData.append('preview_user_id', previewUserId);
        formData.append('action', 'save_certificate_layout_single');

        fetch('../../backend/models/generate_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status) {
                        alert('Layout saved for the selected participant.');
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
                alert('Failed to save layout.');
            });
    });

    /***********************************************************
     * 8. Save All Layouts (for every participant)
     ***********************************************************/
    document.getElementById('saveAllLayoutsBtn').addEventListener('click', () => {
        const layoutImage = canvas.toDataURL({
            format: 'png',
            quality: 1.0
        });
        const canvasJSON = JSON.stringify(canvas.toJSON());
        const formData = new FormData(document.getElementById('certificateForm'));
        formData.delete('add_image');
        formData.append('final_image', layoutImage);
        formData.append('canvas_json', canvasJSON);
        formData.append('action', 'save_certificate_layout_all');

        fetch('../../backend/models/generate_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status) {
                        alert('Layouts saved for all participants.');
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
                alert('Failed to save layouts.');
            });
    });

    /***********************************************************
     * 9. Preview PDF
     ***********************************************************/
    document.getElementById('previewPdfBtn').addEventListener('click', () => {
        // Store the original texts of placeholders before rendering
        const originalTexts = {};
        canvas.getObjects('i-text').forEach(function(obj) {
            if (obj.placeholderType === 'name') {
                originalTexts[obj.id] = obj.text;
            }
        });

        // Make sure name is updated before generating preview
        updateNamePlaceholder();

        const layoutImage = canvas.toDataURL({
            format: 'png',
            quality: 1.0
        });
        const canvasJSON = JSON.stringify(canvas.toJSON());
        const formData = new FormData(document.getElementById('certificateForm'));
        formData.delete('add_image');
        formData.append('final_image', layoutImage);
        formData.append('layout_json', canvasJSON);
        formData.append('preview_user_id', document.getElementById('previewUserId').value);
        formData.append('action', 'preview_certificate');

        fetch('../../backend/models/generate_certificate.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.status && data.pdf_base64) {
                    document.getElementById('pdfPreviewFrame').src = "data:application/pdf;base64," + data
                        .pdf_base64;
                    document.getElementById('pdfPreviewModal').style.display = 'block';
                } else {
                    alert('Preview error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to preview PDF.');
            });
    });

    /***********************************************************
     * 10. Close PDF Preview
     ***********************************************************/
    document.getElementById('closePreviewBtn').addEventListener('click', () => {
        document.getElementById('pdfPreviewModal').style.display = 'none';
    });

    /***********************************************************
     * 11. Load saved canvas JSON for editing (if available)
     ***********************************************************/
    window.addEventListener('load', () => {
        console.log("Loading saved canvas JSON for training_id:", TRAINING_ID);

        // First load participant dropdown
        initParticipantDropdown();

        // Then load the saved layout
        fetch(
                `../../backend/models/generate_certificate.php?action=load_certificate_layout&training_id=${TRAINING_ID}`
            )
            .then(response => response.text())
            .then(text => {
                console.log("Response from load_certificate_layout:", text);
                try {
                    const data = JSON.parse(text);
                    if (data.status && data.data && data.data.canvas_json) {
                        console.log("Found canvas_json, loading into editor");
                        try {
                            // Parse the JSON once to identify and modify name placeholders
                            const jsonObj = JSON.parse(data.data.canvas_json);

                            // Examine the objects and prepare them for loading
                            if (jsonObj.objects && Array.isArray(jsonObj.objects)) {
                                jsonObj.objects.forEach(obj => {
                                    // Reset names to placeholder values if needed
                                    if (obj.type === 'i-text') {
                                        // Check for potential name patterns
                                        if (obj.placeholderType === 'name' ||
                                            (obj.text && obj.text.includes('[Name]')) ||
                                            // Also check if this looks like an actual name
                                            (obj.text &&
                                                /^[A-Z][a-z]+ [A-Z][a-z]+(\s+[A-Z][a-z]+)?$/.test(
                                                    obj.text))) {

                                            console.log("Found name field in JSON:", obj.text);
                                            // Save original text but mark as placeholderType
                                            obj.originalText = obj.text;
                                            obj.placeholderType = 'name';
                                            // Reset to placeholder during loading
                                            obj.text = '[Name]';
                                        }
                                    }
                                });
                            }

                            // Load the modified JSON
                            canvas.loadFromJSON(JSON.stringify(jsonObj), () => {
                                console.log("Canvas JSON loaded successfully");

                                // Double-check all text objects after loading
                                canvas.getObjects('i-text').forEach((obj, index) => {
                                    obj.id = `text_${index}`; // Assign unique IDs

                                    // Re-mark any name placeholders based on our criteria
                                    if (obj.placeholderType === 'name' ||
                                        (obj.text && obj.text.includes('[Name]')) ||
                                        (obj.originalText && obj.originalText.includes(
                                            '[Name]'))) {
                                        console.log("Found name placeholder after loading:",
                                            obj);
                                        obj.placeholderType = 'name';
                                    }
                                });

                                // Now update with the selected participant's name
                                updateNamePlaceholder();
                                canvas.renderAll();
                            });
                        } catch (e) {
                            console.error("Error loading canvas JSON:", e);
                        }
                    } else {
                        console.log("No saved canvas_json found");
                    }
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                }
            })
            .catch(error => {
                console.error("Error loading saved layout:", error);
            });
    });

    // Make sure event listeners are properly attached
    document.getElementById('previewUserId').addEventListener('change', function() {
        console.log("🔄 Dropdown changed to:", this.options[this.selectedIndex].text);
        updateNamePlaceholder();
    });

    // Enhance navigation between participants
    document.getElementById('prevCertBtn').addEventListener('click', () => {
        const select = document.getElementById('previewUserId');
        const currentIndex = select.selectedIndex;
        if (currentIndex > 0) {
            select.selectedIndex = currentIndex - 1;
            console.log("⬅️ Selected previous participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder(); // Force update
        }
    });

    document.getElementById('nextCertBtn').addEventListener('click', () => {
        const select = document.getElementById('previewUserId');
        const currentIndex = select.selectedIndex;
        if (currentIndex < select.options.length - 1) {
            select.selectedIndex = currentIndex + 1;
            console.log("➡️ Selected next participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder(); // Force update
        }
    });

    // Initialize the dropdown and name when the page loads
    function initParticipantDropdown() {
        console.log("Initializing participant dropdown");
        const dropdown = document.getElementById('previewUserId');
        if (dropdown && dropdown.options.length > 0) {
            // Select first option if none selected
            if (dropdown.selectedIndex < 0) {
                dropdown.selectedIndex = 0;
            }
            console.log("Selected participant:", dropdown.options[dropdown.selectedIndex].text);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        console.log("DOMContentLoaded - initializing");
        initParticipantDropdown();
        updateNamePlaceholder();
    });

    // Enhanced navigation between participants
    document.getElementById('prevCertBtn').addEventListener('click', () => {
        const select = document.getElementById('previewUserId');
        const currentIndex = select.selectedIndex;
        if (currentIndex > 0) {
            select.selectedIndex = currentIndex - 1;
            console.log("Selected previous participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder();
        }
    });

    document.getElementById('nextCertBtn').addEventListener('click', () => {
        const select = document.getElementById('previewUserId');
        const currentIndex = select.selectedIndex;
        if (currentIndex < select.options.length - 1) {
            select.selectedIndex = currentIndex + 1;
            console.log("Selected next participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder();
        }
    });

    // Initialize the name with the current selection when the page loads
    document.addEventListener('DOMContentLoaded', () => {
        console.log("DOMContentLoaded - updating name placeholder");
        updateNamePlaceholder();
    });

    // Enhanced updateNamePlaceholder function with debug logging
    function updateNamePlaceholder() {
        const select = document.getElementById('previewUserId');
        const selectedText = select.options[select.selectedIndex].text;
        console.log("Updating name placeholder to:", selectedText);

        // Find the name placeholder - could be from template or loaded from JSON
        let namePlaceholder = null;

        // Search by property first
        canvas.getObjects('i-text').forEach(function(obj) {
            if (obj.placeholderType === 'name') {
                console.log("Found placeholder by placeholderType");
                namePlaceholder = obj;
            }
        });

        // If not found, search by text content
        if (!namePlaceholder) {
            canvas.getObjects('i-text').forEach(function(obj) {
                if (obj.text && (obj.text === '[Name]' || obj.text.includes('[Name]'))) {
                    console.log("Found placeholder by text content");
                    obj.placeholderType = 'name'; // Mark it for future
                    namePlaceholder = obj;
                }
            });
        }

        if (namePlaceholder) {
            console.log("Setting placeholder text to:", selectedText);
            namePlaceholder.set('text', selectedText);
            canvas.renderAll();
        } else {
            console.log("No name placeholder found!");
        }
    }
    </script>
</body>

</html>