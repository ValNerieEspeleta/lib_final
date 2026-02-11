<?php
// Include database connection
include '../includes/dbcon.php';
?>
/**
 * Edit and Delete Buttons with Inline Forms
 * Fully Functional Version
 */
?>

<!-- Inline Edit Forms Container -->
<div id="editFormsContainer"></div>

<!-- Styling for Edit/Delete Buttons -->
<style>
  .btn-edit, .btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 6px;
    cursor: pointer !important;
    font-size: 16px;
    transition: all 0.2s ease;
    padding: 0;
    margin: 0 2px;
    background-color: #e3f2fd;
    color: #1976d2;
  }

  .btn-edit {
    background-color: #e3f2fd;
    color: #1976d2;
  }

  .btn-edit:hover {
    background-color: #1976d2;
    color: white;
    transform: scale(1.15);
  }

  .btn-delete {
    background-color: #ffebee;
    color: #d32f2f;
  }

  .btn-delete:hover {
    background-color: #d32f2f;
    color: white;
    transform: scale(1.15);
  }

  .action-icons {
    display: flex;
    gap: 2px;
    justify-content: center;
  }

  .edit-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
  }

  .edit-modal.show {
    display: block;
  }

  .edit-modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }

  .edit-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 10px;
  }

  .edit-modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    border: none;
    background: none;
    padding: 0;
    width: 30px;
    height: 30px;
  }

  .edit-modal-close:hover {
    color: #000;
  }

  .edit-form-group {
    margin-bottom: 15px;
  }

  .edit-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
  }

  .edit-form-group input,
  .edit-form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
  }

  .edit-form-group input:focus,
  .edit-form-group textarea:focus {
    outline: none;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
  }

  .edit-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
  }

  .edit-modal-footer button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
  }

  .btn-secondary {
    background-color: #e0e0e0;
    color: #333;
  }

  .btn-secondary:hover {
    background-color: #d0d0d0;
  }

  .btn-primary {
    background-color: #1976d2;
    color: white;
  }

  .btn-primary:hover {
    background-color: #1565c0;
  }
</style>

<!-- JavaScript for Edit and Delete -->
<script>
function openEditModal(type, data) {
  if (type === 'student') {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('rfid_number').value = data.rfid;
    document.getElementById('first_name').value = data.firstname;
    document.getElementById('last_name').value = data.lastname;
    document.getElementById('year_id').value = data.year;
    document.getElementById('section_id').value = data.section;
    document.getElementById('email').value = data.email;
    document.getElementById('address').value = data.address;
    
    // Ensure dropdowns are properly set
    if(document.getElementById('year_id')){
      document.getElementById('year_id').value = data.year || '';
    }
    if(document.getElementById('section_id')){
      document.getElementById('section_id').value = data.section || '';
    }
    
    $('#editStudentModal').modal('show');
  }
}

function deleteUser(id, type) {
  if(confirm('Are you sure you want to delete this user?')) {
    window.location.href = '?delete_id=' + id + '&type=' + type;
  }
}
  const modal = document.createElement('div');
  modal.className = 'edit-modal show';
  modal.id = 'editModal_' + data.id;

  let formHTML = '';
  
  if (type === 'student') {
    formHTML = `
      <div class="edit-modal-content">
        <div class="edit-modal-header">
          <h5 style="margin: 0;">Edit Student</h5>
          <button class="edit-modal-close" onclick="document.getElementById('editModal_${data.id}').remove();">&times;</button>
        </div>
        <form method="POST" onsubmit="return submitEditForm(event, 'student');">
          <input type="hidden" name="edit_id" value="${data.id}">
          <input type="hidden" name="edit_type" value="student">
          
          <div class="edit-form-group">
            <label>RFID Number:</label>
            <input type="text" name="rfid_number" value="${data.rfid || ''}">
          </div>
          
          <div class="edit-form-group">
            <label>First Name:</label>
            <input type="text" name="first_name" value="${data.firstname || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Last Name:</label>
            <input type="text" name="last_name" value="${data.lastname || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Year Level:</label>
            <input type="number" name="year_id" value="${data.year || ''}">
          </div>
          
          <div class="edit-form-group">
            <label>Section:</label>
            <input type="number" name="section_id" value="${data.section || ''}">
          </div>
          
          <div class="edit-form-group">
            <label>Email:</label>
            <input type="email" name="email" value="${data.email || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Address:</label>
            <textarea name="address" rows="2">${data.address || ''}</textarea>
          </div>
          
          <div class="edit-modal-footer">
            <button type="button" class="btn-secondary" onclick="document.getElementById('editModal_${data.id}').remove();">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    `;
  } else if (type === 'regular') {
    formHTML = `
      <div class="edit-modal-content">
        <div class="edit-modal-header">
          <h5 style="margin: 0;">Edit Regular User</h5>
          <button class="edit-modal-close" onclick="document.getElementById('editModal_${data.id}').remove();">&times;</button>
        </div>
        <form method="POST" onsubmit="return submitEditForm(event, 'regular');">
          <input type="hidden" name="edit_id" value="${data.id}">
          <input type="hidden" name="edit_type" value="regular">
          
          <div class="edit-form-group">
            <label>First Name:</label>
            <input type="text" name="firstname" value="${data.firstname || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Last Name:</label>
            <input type="text" name="lastname" value="${data.lastname || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Email:</label>
            <input type="email" name="email" value="${data.email || ''}" required>
          </div>
          
          <div class="edit-form-group">
            <label>Address:</label>
            <textarea name="address" rows="2">${data.address || ''}</textarea>
          </div>
          
          <div class="edit-modal-footer">
            <button type="button" class="btn-secondary" onclick="document.getElementById('editModal_${data.id}').remove();">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    `;
  }
  
  modal.innerHTML = formHTML;
  document.body.appendChild(modal);
  
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

function submitEditForm(e, type) {
  e.preventDefault();
  const form = e.target;
  form.submit();
}

function deleteUser(id, type) {
  if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
    window.location.href = '?delete_id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type);
  }
}
</script>