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
         * 3. Update Name Placeholder based on Participant selection - FIXED VERSION
         ***********************************************************/
        function updateNamePlaceholder() {
            const select = document.getElementById('previewUserId');
            if (!select || select.selectedIndex < 0) {
                console.error("No participant selected in dropdown");
                return;
            }

            const selectedText = select.options[select.selectedIndex].text;
            console.log("🔍 Attempting to update name placeholder to:", selectedText);

            // DEBUG: List all text objects on the canvas
            console.log("All text objects in canvas:", canvas.getObjects('i-text').map(obj => ({
                text: obj.text,
                type: obj.type,
                placeholderType: obj.placeholderType,
                fontSize: obj.fontSize,
                position: {
                    top: obj.top,
                    left: obj.left
                }
            })));

            // Find name placeholders in the canvas
            let namePlaceholders = [];

            // Method 1: Find by placeholderType property
            canvas.getObjects('i-text').forEach(function(obj) {
                if (obj.placeholderType === 'name') {
                    console.log("✓ Found placeholder by placeholderType:", obj);
                    namePlaceholders.push(obj);
                }
            });

            // Method 2: Find by text content
            if (namePlaceholders.length === 0) {
                canvas.getObjects('i-text').forEach(function(obj) {
                    if (obj.text && (
                            obj.text === '[Name]' ||
                            obj.text.includes('[Name]') ||
                            (obj.originalText && obj.originalText.includes('[Name]'))
                        )) {
                        console.log("✓ Found placeholder by text content:", obj);
                        // Mark it so we find it next time
                        obj.placeholderType = 'name';
                        obj.originalText = obj.originalText || obj.text;
                        namePlaceholders.push(obj);
                    }
                });
            }

            // Method 3: Find by name-like content (if object contains a real name)
            if (namePlaceholders.length === 0) {
                canvas.getObjects('i-text').forEach(function(obj) {
                    // Check if text looks like a name (two capitalized words)
                    if (obj.text && /^[A-Z][a-z]+ [A-Z][a-z]+/.test(obj.text)) {
                        console.log("✓ Found placeholder by name pattern:", obj);
                        obj.placeholderType = 'name';
                        obj.originalText = obj.originalText || obj.text;
                        namePlaceholders.push(obj);
                    }
                });
            }

            // Method 4: Find by prominent text position and size
            if (namePlaceholders.length === 0) {
                let prominentObjects = [];
                canvas.getObjects('i-text').forEach(function(obj) {
                    // Score each text by how likely it is to be a name
                    let score = 0;

                    // Centered text is more likely to be a name
                    if (obj.originX === 'center') score += 5;

                    // Text in the middle third of the certificate is likely a name
                    if (obj.top > canvas.height * 0.2 && obj.top < canvas.height * 0.7) score += 3;

                    // Larger text is more likely to be a name
                    score += Math.min(10, obj.fontSize / 5);

                    prominentObjects.push({
                        obj,
                        score
                    });
                });

                // Sort by score (descending)
                prominentObjects.sort((a, b) => b.score - a.score);

                if (prominentObjects.length > 0) {
                    const topObj = prominentObjects[0].obj;
                    console.log("✓ Found placeholder by prominence (score=" + prominentObjects[0].score + "):", topObj);
                    topObj.placeholderType = 'name';
                    topObj.originalText = topObj.originalText || topObj.text;
                    namePlaceholders.push(topObj);
                }
            }

            // Update all found placeholders
            if (namePlaceholders.length > 0) {
                namePlaceholders.forEach(function(obj) {
                    // Save original text if this is a first-time update
                    if (!obj.originalText) obj.originalText = obj.text;

                    console.log("✓ Setting placeholder text from:", obj.text, "to:", selectedText);
                    obj.set('text', selectedText);
                });
                canvas.renderAll();
                console.log("✅ Updated", namePlaceholders.length, "name placeholder(s)");
            } else {
                console.warn("❌ No name placeholder found in the canvas!");
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
            // Show loading indicator
            const btn = document.getElementById('saveAllLayoutsBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Saving...';
            btn.disabled = true;

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
                .then(resp => {
                    if (!resp.ok) {
                        throw new Error(`Server error: ${resp.status}`);
                    }
                    return resp.text();
                })
                .then(text => {
                    try {
                        // Check if response is HTML instead of JSON (common PHP error output)
                        if (text.trim().startsWith('<')) {
                            console.error("Server returned HTML instead of JSON:", text);
                            throw new Error("Server returned an error page instead of JSON");
                        }

                        const data = JSON.parse(text);
                        if (data.status) {
                            alert('Layouts saved for all participants.');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (err) {
                        console.error("JSON parse error:", err);
                        console.error("Raw server response:", text);
                        alert("Error processing server response. Check console for details.");
                    }
                })
                .catch(err => {
                    console.error('Error saving layouts:', err);
                    alert('Failed to save layouts: ' + err.message);
                })
                .finally(() => {
                    // Reset button state
                    btn.innerHTML = originalText;
                    btn.disabled = false;
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
         * 11. Load saved canvas JSON for editing (if available) - FIXED VERSION
         ***********************************************************/
        window.addEventListener('load', () => {
            console.log("Loading saved canvas JSON for training_id:", TRAINING_ID);

            // First load participant dropdown
            initParticipantDropdown();

            // Debug check of default canvas state
            console.log("Default canvas objects:", canvas.getObjects().map(obj => ({
                type: obj.type,
                text: obj.type === 'i-text' ? obj.text : null,
                placeholderType: obj.placeholderType
            })));

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
                                const jsonObj = JSON.parse(data.data.canvas_json);

                                // Pre-process the JSON to identify name placeholders
                                if (jsonObj.objects && Array.isArray(jsonObj.objects)) {
                                    let foundNamePlaceholder = false;

                                    console.log("Objects in loaded JSON:", jsonObj.objects.length);

                                    jsonObj.objects.forEach(obj => {
                                        if (obj.type === 'i-text' || obj.type === 'textbox') {
                                            // Check for explicit name placeholders
                                            if (obj.placeholderType === 'name') {
                                                console.log("Found pre-marked name placeholder:", obj
                                                    .text);
                                                foundNamePlaceholder = true;
                                                // Keep the placeholderType but store the original text
                                                obj.originalText = obj.text;
                                            }
                                            // Check for text with [Name] or similar patterns
                                            else if (obj.text && (
                                                    obj.text === '[Name]' ||
                                                    obj.text.includes('[Name]')
                                                )) {
                                                console.log("Found name placeholder by text:", obj
                                                    .text);
                                                obj.placeholderType = 'name';
                                                obj.originalText = obj.text;
                                                foundNamePlaceholder = true;
                                            }
                                            // Check if text appears to be a name (two capitalized words)
                                            else if (obj.text && /^[A-Z][a-z]+ [A-Z][a-z]+/.test(obj
                                                    .text)) {
                                                console.log("Found name pattern:", obj.text);
                                                obj.placeholderType = 'name';
                                                obj.originalText = obj.text;
                                                foundNamePlaceholder = true;
                                            }
                                        }
                                    });

                                    // If no name placeholder was found but there are text objects, 
                                    // we should use a heuristic to identify the most likely one
                                    if (!foundNamePlaceholder) {
                                        let prominentTexts = [];

                                        jsonObj.objects.forEach(obj => {
                                            if (obj.type === 'i-text' || obj.type === 'textbox') {
                                                let score = 0;

                                                // Centered text is more likely to be a name
                                                if (obj.originX === 'center') score += 5;

                                                // Text in the middle third of certificate
                                                if (obj.top > canvas.height * 0.25 && obj.top < canvas
                                                    .height * 0.6) score += 3;

                                                // Larger text is more likely to be a name
                                                score += Math.min(10, obj.fontSize / 5);

                                                prominentTexts.push({
                                                    obj,
                                                    score
                                                });
                                            }
                                        });

                                        // Sort by score
                                        prominentTexts.sort((a, b) => b.score - a.score);

                                        if (prominentTexts.length > 0) {
                                            const topObj = prominentTexts[0].obj;
                                            console.log("Found likely name placeholder by prominence:", topObj);
                                            topObj.placeholderType = 'name';
                                            topObj.originalText = topObj.text;
                                        }
                                    }
                                }

                                // Load the enhanced JSON
                                canvas.loadFromJSON(JSON.stringify(jsonObj), () => {
                                    console.log("Canvas JSON loaded successfully");

                                    // Verify which objects are on the canvas
                                    console.log("Loaded text objects:", canvas.getObjects('i-text').map(
                                        obj => ({
                                            text: obj.text,
                                            placeholderType: obj.placeholderType,
                                            originalText: obj.originalText
                                        })));

                                    // Now update with the selected participant's name
                                    updateNamePlaceholder();
                                    canvas.renderAll();
                                });
                            } catch (e) {
                                console.error("Error loading canvas JSON:", e);
                                // Fall back to the default canvas
                                console.log("Falling back to default canvas");
                            }
                        } else {
                            console.log("No saved canvas_json found, using default");
                            // Still update name placeholder on the default canvas
                            updateNamePlaceholder();
                        }
                    } catch (e) {
                        console.error("Error parsing JSON response:", e);
                        // Still update name placeholder on the default canvas
                        updateNamePlaceholder();
                    }
                })
                .catch(error => {
                    console.error("Error loading saved layout:", error);
                    // Still update name placeholder on the default canvas
                    updateNamePlaceholder();
                });
        });

        // Initialize the dropdown and name placeholder once
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

        // REMOVE DUPLICATE EVENT LISTENERS AND FUNCTIONS

        // Clean up event listeners to prevent duplicates
        document.getElementById('previewUserId').outerHTML = document.getElementById('previewUserId').outerHTML;
        document.getElementById('prevCertBtn').outerHTML = document.getElementById('prevCertBtn').outerHTML;
        document.getElementById('nextCertBtn').outerHTML = document.getElementById('nextCertBtn').outerHTML;

        // Re-add event listeners after cleanup
        document.getElementById('previewUserId').addEventListener('change', function() {
            console.log("Dropdown changed to:", this.options[this.selectedIndex].text);
            updateNamePlaceholder();
        });

        // Modify the previous button to loop through participants
        document.getElementById('prevCertBtn').addEventListener('click', function() {
            const select = document.getElementById('previewUserId');
            const currentIndex = select.selectedIndex;
            const totalOptions = select.options.length;

            if (currentIndex > 0) {
                // Not at the beginning, just go to previous
                select.selectedIndex = currentIndex - 1;
            } else {
                // At the beginning (index 0), loop to the end
                select.selectedIndex = totalOptions - 1;
            }

            console.log("Selected previous participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder();
        });

        // Modify the next button to loop through participants
        document.getElementById('nextCertBtn').addEventListener('click', function() {
            const select = document.getElementById('previewUserId');
            const currentIndex = select.selectedIndex;
            const totalOptions = select.options.length;

            if (currentIndex < totalOptions - 1) {
                // Not at the end, just go to next
                select.selectedIndex = currentIndex + 1;
            } else {
                // At the end, loop back to beginning
                select.selectedIndex = 0;
            }

            console.log("Selected next participant:", select.options[select.selectedIndex].text);
            updateNamePlaceholder();
        });

        // Make sure we initialize only once on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOMContentLoaded - running one-time initialization");
            initParticipantDropdown();
            updateNamePlaceholder();
        }, {
            once: true
        });

        // Delete duplicate functions to avoid confusion
        // Only the top-level updateNamePlaceholder function remains
    </script>
</body>

</html>