<?php
/**
 * Bloc upload pièce d'identité recto / verso (demande certification).
 */
?>
<div class="cert-upload-grid cert-upload-grid--piece-id">
    <div class="cert-upload">
        <label>
            <i class="fas fa-id-card"></i>
            Pièce d'identité — recto <span class="req">*</span>
            <input type="file" name="photo_piece_identite" accept="image/jpeg,image/png,image/webp" required>
            <span class="cert-upload__name">Face avant (CNI, passeport…)</span>
        </label>
    </div>
    <div class="cert-upload">
        <label>
            <i class="fas fa-id-card"></i>
            Pièce d'identité — verso <span class="req">*</span>
            <input type="file" name="photo_piece_identite_verso" accept="image/jpeg,image/png,image/webp" required>
            <span class="cert-upload__name">Face arrière</span>
        </label>
    </div>
</div>
<p class="cert-upload-hint">CNI, passeport ou carte consulaire : les deux faces sont obligatoires.</p>
