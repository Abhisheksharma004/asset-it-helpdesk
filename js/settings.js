/**
 * ==========================================================================
 * VIROS IT Asset & Service Desk - Settings Page Interactivity
 * Focuses strictly on Organization Setting & User Setting tabs.
 * Supports Display View Mode, Inline Edit Mode, and Real Brand Asset Uploads.
 * ==========================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ----------------------------------------------------------------------
    // 0. Persistent Brand Assets (Logo & Favicon) from Client Storage
    // ----------------------------------------------------------------------
    const defaultLogoSrc = 'assets/images/logo.png';
    const viewLogoImg = document.getElementById('viewLogoImg');
    const logoPreviewImg = document.getElementById('logoPreviewImg');
    const viewFaviconImg = document.getElementById('viewFaviconImg');
    const faviconPreviewImg = document.getElementById('faviconPreviewImg');
    const sidebarLogoImg = document.querySelector('.brand-logo-wrap img');

    // ----------------------------------------------------------------------
    // 1. Tab Navigation & Hash Routing (Organization & User settings)
    // ----------------------------------------------------------------------
    const tabButtons = document.querySelectorAll('.settings-nav-btn');
    const tabPanels = document.querySelectorAll('.settings-tab-panel');
    const unsavedDock = document.getElementById('unsavedChangesDock');
    let hasUnsavedChanges = false;

    function switchTab(targetId, updateHash = true) {
        if (!targetId) return;

        tabButtons.forEach(btn => {
            const isTarget = btn.getAttribute('data-tab') === targetId;
            btn.classList.toggle('active', isTarget);
        });

        tabPanels.forEach(panel => {
            const isTarget = panel.id === targetId;
            panel.classList.toggle('active', isTarget);
        });

        if (updateHash) {
            window.location.hash = targetId.replace('panel-', '');
        }

        // Scroll to top of settings on mobile if tab switched
        if (window.innerWidth <= 1024) {
            const mainContent = document.querySelector('.settings-content-wrapper');
            if (mainContent) {
                mainContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-tab');
            switchTab(targetId);
        });
    });

    // Check URL hash on initial load
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        let hashId = 'panel-' + hash;
        if (hash === 'organization' || hash === 'general') {
            hashId = 'panel-organization';
        } else if (hash === 'users' || hash === 'user' || hash === 'security') {
            hashId = 'panel-users';
        }

        if (document.getElementById(hashId)) {
            switchTab(hashId, false);
        }
    }

    // ----------------------------------------------------------------------
    // 2. Organization View Mode & Edit Mode Toggling
    // ----------------------------------------------------------------------
    const btnEditOrg = document.getElementById('btnEditOrg');
    const orgEditActions = document.getElementById('orgEditActions');
    const btnCancelOrgEdit = document.getElementById('btnCancelOrgEdit');
    const btnSaveOrgEdit = document.getElementById('btnSaveOrgEdit');
    const orgViewMode = document.getElementById('orgViewMode');
    const orgEditMode = document.getElementById('orgEditMode');

    const inputOrgName = document.getElementById('settingCompanyName');
    const inputOrgEmail = document.getElementById('settingSupportEmail');
    const inputOrgPhone = document.getElementById('settingSupportPhone');

    const viewOrgName = document.getElementById('viewOrgName');
    const viewOrgEmail = document.getElementById('viewOrgEmail');
    const viewOrgPhone = document.getElementById('viewOrgPhone');

    if (btnEditOrg && orgViewMode && orgEditMode) {
        btnEditOrg.addEventListener('click', function () {
            // Populate inputs with current view text
            if (inputOrgName && viewOrgName) inputOrgName.value = viewOrgName.textContent.trim();
            if (inputOrgEmail && viewOrgEmail) inputOrgEmail.value = viewOrgEmail.textContent.trim();
            if (inputOrgPhone && viewOrgPhone) inputOrgPhone.value = viewOrgPhone.textContent.trim();

            orgViewMode.style.display = 'none';
            orgEditMode.style.display = 'block';
            btnEditOrg.style.display = 'none';
            if (orgEditActions) orgEditActions.style.display = 'flex';
        });
    }

    if (btnCancelOrgEdit) {
        btnCancelOrgEdit.addEventListener('click', function () {
            orgViewMode.style.display = 'block';
            orgEditMode.style.display = 'none';
            if (btnEditOrg) btnEditOrg.style.display = 'inline-flex';
            if (orgEditActions) orgEditActions.style.display = 'none';
            markClean();
        });
    }

    let resetLogoRequested = false;
    let resetFaviconRequested = false;

    if (btnSaveOrgEdit) {
        btnSaveOrgEdit.addEventListener('click', function () {
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = `<svg width="14" height="14" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Saving...`;

            const formData = new FormData();
            formData.append('organization_legal_name', inputOrgName ? inputOrgName.value.trim() : '');
            formData.append('support_desk_email', inputOrgEmail ? inputOrgEmail.value.trim() : '');
            formData.append('it_helpdesk_helpline', inputOrgPhone ? inputOrgPhone.value.trim() : '');

            if (inputLogoFile && inputLogoFile.files && inputLogoFile.files[0]) {
                formData.append('logo_file', inputLogoFile.files[0]);
            } else if (resetLogoRequested) {
                formData.append('reset_logo', '1');
            }

            if (inputFaviconFile && inputFaviconFile.files && inputFaviconFile.files[0]) {
                formData.append('favicon_file', inputFaviconFile.files[0]);
            } else if (resetFaviconRequested) {
                formData.append('reset_favicon', '1');
            }

            fetch('api/organization_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                btnSaveOrgEdit.disabled = false;
                btnSaveOrgEdit.innerHTML = originalHtml;

                if (res.success && res.data) {
                    const data = res.data;

                    // Update View Mode text values
                    if (viewOrgName) viewOrgName.textContent = data.organization_legal_name;
                    if (viewOrgEmail) viewOrgEmail.textContent = data.support_desk_email;
                    if (viewOrgPhone) viewOrgPhone.textContent = data.it_helpdesk_helpline;

                    // Update Input values to sync
                    if (inputOrgName) inputOrgName.value = data.organization_legal_name;
                    if (inputOrgEmail) inputOrgEmail.value = data.support_desk_email;
                    if (inputOrgPhone) inputOrgPhone.value = data.it_helpdesk_helpline;

                    // Update Logo images
                    if (data.primary_brand_logo) {
                        if (viewLogoImg) viewLogoImg.src = data.primary_brand_logo;
                        if (logoPreviewImg) logoPreviewImg.src = data.primary_brand_logo;
                        if (sidebarLogoImg) sidebarLogoImg.src = data.primary_brand_logo;
                        localStorage.removeItem('viros_custom_logo');
                    }

                    // Update Favicon images
                    if (data.browser_favicon) {
                        if (viewFaviconImg) viewFaviconImg.src = data.browser_favicon;
                        if (faviconPreviewImg) faviconPreviewImg.src = data.browser_favicon;
                        localStorage.removeItem('viros_custom_favicon');
                    }

                    // Reset flags & file inputs
                    resetLogoRequested = false;
                    resetFaviconRequested = false;
                    if (inputLogoFile) inputLogoFile.value = '';
                    if (inputFaviconFile) inputFaviconFile.value = '';

                    // Switch back to View Mode
                    orgViewMode.style.display = 'block';
                    orgEditMode.style.display = 'none';
                    if (btnEditOrg) btnEditOrg.style.display = 'inline-flex';
                    if (orgEditActions) orgEditActions.style.display = 'none';

                    markClean();

                    if (typeof showToast === 'function') {
                        showToast('Organization settings updated in database.', 'success', 'Saved');
                    } else {
                        alert('Organization settings updated in database successfully!');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(res.message || 'Failed to update settings.', 'error', 'Error');
                    } else {
                        alert(res.message || 'Failed to update settings.');
                    }
                }
            })
            .catch(err => {
                btnSaveOrgEdit.disabled = false;
                btnSaveOrgEdit.innerHTML = originalHtml;
                console.error(err);
                if (typeof showToast === 'function') {
                    showToast('Network error while saving settings.', 'error', 'Error');
                } else {
                    alert('Network error while saving settings.');
                }
            });
        });
    }

    // ----------------------------------------------------------------------
    // 3. Logo & Favicon Real File Upload Handlers
    // ----------------------------------------------------------------------
    const btnTriggerLogoUpload = document.getElementById('btnTriggerLogoUpload');
    const inputLogoFile = document.getElementById('inputLogoFile');
    const btnResetLogo = document.getElementById('btnResetLogo');

    const btnTriggerFaviconUpload = document.getElementById('btnTriggerFaviconUpload');
    const inputFaviconFile = document.getElementById('inputFaviconFile');
    const btnResetFavicon = document.getElementById('btnResetFavicon');

    // Trigger Logo File Picker
    if (btnTriggerLogoUpload && inputLogoFile) {
        btnTriggerLogoUpload.addEventListener('click', function () {
            inputLogoFile.click();
        });

        inputLogoFile.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];

                if (!file.type.startsWith('image/')) {
                    if (typeof showToast === 'function') {
                        showToast('Please select a valid image file (PNG, JPG, SVG, WebP).', 'error', 'Invalid File');
                    } else {
                        alert('Please select a valid image file.');
                    }
                    return;
                }

                resetLogoRequested = false;
                markDirty();

                const reader = new FileReader();
                reader.onload = function (e) {
                    const dataUrl = e.target.result;
                    if (logoPreviewImg) logoPreviewImg.src = dataUrl;
                    if (viewLogoImg) viewLogoImg.src = dataUrl;
                    if (sidebarLogoImg) sidebarLogoImg.src = dataUrl;

                    if (typeof showToast === 'function') {
                        showToast(`Logo "${file.name}" ready to save.`, 'info', 'Logo Selected');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Reset Logo
    if (btnResetLogo) {
        btnResetLogo.addEventListener('click', function () {
            resetLogoRequested = true;
            markDirty();

            if (logoPreviewImg) logoPreviewImg.src = defaultLogoSrc;
            if (viewLogoImg) viewLogoImg.src = defaultLogoSrc;
            if (sidebarLogoImg) sidebarLogoImg.src = defaultLogoSrc;
            if (inputLogoFile) inputLogoFile.value = '';

            if (typeof showToast === 'function') {
                showToast('Brand logo reset to default system logo. Click Save Changes to commit.', 'info', 'Logo Reset');
            }
        });
    }

    // Trigger Favicon File Picker
    if (btnTriggerFaviconUpload && inputFaviconFile) {
        btnTriggerFaviconUpload.addEventListener('click', function () {
            inputFaviconFile.click();
        });

        inputFaviconFile.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];

                resetFaviconRequested = false;
                markDirty();

                const reader = new FileReader();
                reader.onload = function (e) {
                    const dataUrl = e.target.result;
                    if (faviconPreviewImg) faviconPreviewImg.src = dataUrl;
                    if (viewFaviconImg) viewFaviconImg.src = dataUrl;

                    if (typeof showToast === 'function') {
                        showToast(`Favicon "${file.name}" ready to save.`, 'info', 'Icon Selected');
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Reset Favicon
    if (btnResetFavicon) {
        btnResetFavicon.addEventListener('click', function () {
            resetFaviconRequested = true;
            markDirty();

            if (faviconPreviewImg) faviconPreviewImg.src = defaultLogoSrc;
            if (viewFaviconImg) viewFaviconImg.src = defaultLogoSrc;
            if (inputFaviconFile) inputFaviconFile.value = '';

            if (typeof showToast === 'function') {
                showToast('Browser favicon reset to default icon. Click Save Changes to commit.', 'info', 'Icon Reset');
            }
        });
    }

    // ----------------------------------------------------------------------
    // 4. Unsaved Changes Tracking
    // ----------------------------------------------------------------------
    const settingsInputs = document.querySelectorAll('.settings-form-control, .switch input');
    
    settingsInputs.forEach(input => {
        input.addEventListener('change', function () {
            markDirty();
        });
        if (input.tagName === 'INPUT' && (input.type === 'text' || input.type === 'number' || input.type === 'email' || input.type === 'url')) {
            input.addEventListener('input', function () {
                markDirty();
            });
        }
    });

    function markDirty() {
        if (!hasUnsavedChanges) {
            hasUnsavedChanges = true;
            if (unsavedDock) {
                unsavedDock.classList.add('show');
            }
        }
    }

    function markClean() {
        hasUnsavedChanges = false;
        if (unsavedDock) {
            unsavedDock.classList.remove('show');
        }
    }

    // Discard Changes in Dock
    const discardButtons = document.querySelectorAll('#btnDiscardDock');
    discardButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            markClean();
            if (typeof showToast === 'function') {
                showToast('All unsaved modifications have been discarded.', 'info', 'Changes Reverted');
            }
        });
    });

    // Save Changes in Dock
    const saveDockBtn = document.getElementById('btnSaveSettingsDock');
    if (saveDockBtn) {
        saveDockBtn.addEventListener('click', function () {
            if (btnSaveOrgEdit && orgEditMode && orgEditMode.style.display !== 'none') {
                btnSaveOrgEdit.click();
            } else {
                markClean();
                if (typeof showToast === 'function') {
                    showToast('Settings saved successfully.', 'success', 'Saved');
                }
            }
        });
    }

    // ----------------------------------------------------------------------
    // 5. Add Technician Modal Handling
    // ----------------------------------------------------------------------
    const btnAddTechnician = document.getElementById('btnAddTechnician');
    const addTechnicianModal = document.getElementById('addTechnicianModal');
    const closeTechModalBtn = document.getElementById('closeTechModalBtn');
    const cancelTechModalBtn = document.getElementById('cancelTechModalBtn');
    const addTechnicianForm = document.getElementById('addTechnicianForm');
    const techniciansTableBody = document.getElementById('techniciansTableBody');

    const techModalTitle = document.getElementById('techModalTitle');
    const techEditId = document.getElementById('techEditId');

    function openTechModal() {
        if (techModalTitle) techModalTitle.textContent = 'Add IT Technician / Administrator';
        if (techEditId) techEditId.value = '';
        if (addTechnicianForm) addTechnicianForm.reset();
        const techPassword = document.getElementById('techPassword');
        if (techPassword) {
            techPassword.required = true;
            techPassword.placeholder = 'Enter password';
        }
        const techIsActive = document.getElementById('techIsActive');
        const techStatusLabel = document.getElementById('techStatusLabel');
        if (techIsActive) techIsActive.checked = true;
        if (techStatusLabel) {
            techStatusLabel.textContent = 'Active';
            techStatusLabel.style.color = 'var(--success)';
        }
        const submitBtn = addTechnicianForm?.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.textContent = 'Save Technician';

        if (addTechnicianModal) addTechnicianModal.style.display = 'flex';
    }

    function closeTechModal() {
        if (addTechnicianModal) {
            addTechnicianModal.style.display = 'none';
            if (addTechnicianForm) addTechnicianForm.reset();
            if (techEditId) techEditId.value = '';
            const techPassword = document.getElementById('techPassword');
            if (techPassword) {
                techPassword.required = true;
                techPassword.placeholder = 'Enter password';
            }
            const techIsActive = document.getElementById('techIsActive');
            const techStatusLabel = document.getElementById('techStatusLabel');
            if (techIsActive) techIsActive.checked = true;
            if (techStatusLabel) {
                techStatusLabel.textContent = 'Active';
                techStatusLabel.style.color = 'var(--success)';
            }
        }
    }

    if (btnAddTechnician) btnAddTechnician.addEventListener('click', openTechModal);
    if (closeTechModalBtn) closeTechModalBtn.addEventListener('click', closeTechModal);
    if (cancelTechModalBtn) cancelTechModalBtn.addEventListener('click', closeTechModal);

    // Edit and Delete Technician Button Delegation
    if (techniciansTableBody) {
        techniciansTableBody.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-tech-btn');
            const deleteBtn = e.target.closest('.delete-tech-btn');

            // Handle Edit Click
            if (editBtn) {
                const id = editBtn.getAttribute('data-id');
                const name = editBtn.getAttribute('data-name') || '';
                const email = editBtn.getAttribute('data-email') || '';
                const role = editBtn.getAttribute('data-role') || 'IT Support Desk';
                const phone = editBtn.getAttribute('data-phone') || '';
                const username = editBtn.getAttribute('data-username') || '';
                const statusVal = editBtn.getAttribute('data-status');
                const isAct = (statusVal === null || statusVal === '1' || statusVal === 1);

                if (techModalTitle) techModalTitle.textContent = 'Edit IT Technician / Administrator';
                if (techEditId) techEditId.value = id;

                const nameInput = document.getElementById('techName');
                const emailInput = document.getElementById('techEmail');
                const roleInput = document.getElementById('techRole');
                const phoneInput = document.getElementById('techPhone');
                const userInput = document.getElementById('techUserId');
                const passInput = document.getElementById('techPassword');
                const techIsActive = document.getElementById('techIsActive');
                const techStatusLabel = document.getElementById('techStatusLabel');

                if (nameInput) nameInput.value = name;
                if (emailInput) emailInput.value = email;
                if (roleInput) roleInput.value = role;
                if (phoneInput) phoneInput.value = phone;
                if (userInput) userInput.value = username;
                if (passInput) {
                    passInput.value = '';
                    passInput.required = false;
                    passInput.placeholder = 'Leave blank to keep current password';
                }
                if (techIsActive) techIsActive.checked = isAct;
                if (techStatusLabel) {
                    techStatusLabel.textContent = isAct ? 'Active' : 'Inactive';
                    techStatusLabel.style.color = isAct ? 'var(--success)' : 'var(--danger)';
                }

                const submitBtn = addTechnicianForm?.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Update Technician';

                if (addTechnicianModal) addTechnicianModal.style.display = 'flex';
                return;
            }

            // Handle Delete Click
            if (deleteBtn) {
                const id = deleteBtn.getAttribute('data-id');
                const name = deleteBtn.getAttribute('data-name') || 'this technician';

                if (!confirm(`Are you sure you want to remove technician "${name}"? This action cannot be undone.`)) {
                    return;
                }

                deleteBtn.disabled = true;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch('api/users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    deleteBtn.disabled = false;
                    if (res.success) {
                        const tr = deleteBtn.closest('tr');
                        if (tr) tr.remove();

                        if (techniciansTableBody.children.length === 0) {
                            techniciansTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No technicians registered yet. Click "+ Add Technician" to add one.</td></tr>`;
                        }

                        if (typeof showToast === 'function') {
                            showToast(`Technician "${name}" deleted successfully.`, 'success', 'Technician Deleted');
                        } else {
                            alert(`Technician "${name}" deleted.`);
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(res.message || 'Failed to delete technician.', 'error', 'Error');
                        } else {
                            alert(res.message || 'Failed to delete technician.');
                        }
                    }
                })
                .catch(err => {
                    deleteBtn.disabled = false;
                    console.error(err);
                    if (typeof showToast === 'function') {
                        showToast('Error deleting technician.', 'error', 'Error');
                    } else {
                        alert('Error deleting technician.');
                    }
                });
            }
        });
    }

    if (addTechnicianForm) {
        addTechnicianForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Save Technician';

            const editId = techEditId ? techEditId.value.trim() : '';
            const isEditing = editId !== '';

            const userId = document.getElementById('techUserId')?.value.trim();
            const password = document.getElementById('techPassword')?.value;
            const name = document.getElementById('techName')?.value.trim();
            const email = document.getElementById('techEmail')?.value.trim();
            const role = document.getElementById('techRole')?.value.trim() || 'IT Support Desk';
            const phone = document.getElementById('techPhone')?.value.trim();

            if (!name || !email || !userId || (!isEditing && !password)) {
                if (typeof showToast === 'function') {
                    showToast('Please fill all mandatory fields.', 'error', 'Validation Error');
                } else {
                    alert('Please fill all mandatory fields.');
                }
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<svg width="14" height="14" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg> Saving...`;
            }

            const formData = new FormData();
            formData.append('action', isEditing ? 'edit' : 'create');
            if (isEditing) {
                formData.append('id', editId);
            }
            formData.append('full_name', name);
            formData.append('email', email);
            formData.append('role', role);
            formData.append('phone', phone);
            formData.append('username', userId);
            if (password) {
                formData.append('password', password);
            }
            const techIsActiveInput = document.getElementById('techIsActive');
            const isActiveVal = (techIsActiveInput && !techIsActiveInput.checked) ? 0 : 1;
            formData.append('is_active', isActiveVal);

            fetch('api/users.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }

                if (res.success && res.user) {
                    const uData = res.user;
                    const uId = uData.id || editId;
                    const userIsActive = (uData.is_active !== undefined) ? (parseInt(uData.is_active) === 1) : (isActiveVal === 1);

                    // Calculate initials
                    let initials = '';
                    name.split(' ').forEach(w => {
                        if (w) initials += w[0].toUpperCase();
                    });
                    initials = initials.substring(0, 2) || 'TC';

                    // Role badge class
                    let pillClass = 'medium';
                    if (role === 'Super Admin' || role === 'Administrator') pillClass = 'urgent';
                    else if (role === 'IT Lead') pillClass = 'high';

                    const rowHtml = `
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--cyan-light); color: var(--cyan-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">${escapeHtml(initials)}</div>
                                <div>
                                    <div style="font-weight: 600; color: var(--navy-primary);">${escapeHtml(name)}</div>
                                    <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">${escapeHtml(userId)}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="priority-pill ${pillClass}">${escapeHtml(role)}</span></td>
                        <td>${escapeHtml(email)}</td>
                        <td><span style="color: var(--success); font-weight: 600; font-size: 12px;">✓ Enforced</span></td>
                        <td>
                            <div class="user-status-cell">
                                <label class="switch switch-sm" title="Click to toggle Active / Inactive" style="margin: 0;">
                                    <input type="checkbox" class="user-status-toggle" data-id="${uId}" ${userIsActive ? 'checked' : ''}>
                                    <span class="slider"></span>
                                </label>
                                <span class="user-status-label" style="color: ${userIsActive ? 'var(--success)' : 'var(--danger)'};">
                                    ${userIsActive ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div class="table-actions" style="justify-content: center;">
                                <button type="button" class="action-btn edit-tech-btn" title="Edit Technician" data-id="${uId}" data-name="${escapeHtml(name)}" data-email="${escapeHtml(email)}" data-role="${escapeHtml(role)}" data-phone="${escapeHtml(phone)}" data-username="${escapeHtml(userId)}" data-status="${userIsActive ? 1 : 0}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button type="button" class="action-btn delete-btn delete-tech-btn" title="Delete Technician" data-id="${uId}" data-name="${escapeHtml(name)}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    `;

                    if (isEditing) {
                        const existingRow = techniciansTableBody.querySelector(`tr[data-id="${editId}"]`);
                        if (existingRow) {
                            existingRow.innerHTML = rowHtml;
                        }
                    } else {
                        const newRow = document.createElement('tr');
                        newRow.setAttribute('data-id', uId);
                        newRow.innerHTML = rowHtml;

                        if (techniciansTableBody) {
                            const emptyTr = techniciansTableBody.querySelector('td[colspan]');
                            if (emptyTr) emptyTr.closest('tr')?.remove();
                            techniciansTableBody.appendChild(newRow);
                        }
                    }

                    closeTechModal();

                    if (typeof showToast === 'function') {
                        showToast(isEditing ? `Technician "${name}" updated successfully.` : `Technician "${name}" (${userId}) saved to users table.`, 'success', isEditing ? 'Updated' : 'Technician Saved');
                    } else {
                        alert(`Technician ${name} (${userId}) saved to users table!`);
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(res.message || 'Failed to save technician.', 'error', 'Error');
                    } else {
                        alert(res.message || 'Failed to save technician.');
                    }
                }
            })
            .catch(err => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
                console.error(err);
                if (typeof showToast === 'function') {
                    showToast('Server error while saving technician.', 'error', 'Error');
                } else {
                    alert('Server error while saving technician.');
                }
            });
        });
    }

    // Password Eye Toggle
    document.addEventListener('click', function (e) {
        const toggleBtn = e.target.closest('.password-toggle-btn');
        if (toggleBtn) {
            const targetId = toggleBtn.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    toggleBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
                } else {
                    targetInput.type = 'password';
                    toggleBtn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
                }
            }
        }
    });

    // Modal Account Status Toggle Label update
    const modalTechIsActive = document.getElementById('techIsActive');
    const modalTechStatusLabel = document.getElementById('techStatusLabel');
    if (modalTechIsActive && modalTechStatusLabel) {
        modalTechIsActive.addEventListener('change', function () {
            modalTechStatusLabel.textContent = this.checked ? 'Active' : 'Inactive';
            modalTechStatusLabel.style.color = this.checked ? 'var(--success)' : 'var(--danger)';
        });
    }

    // Direct Account Status Toggle from Table
    document.addEventListener('change', function (e) {
        const statusToggle = e.target.closest('.user-status-toggle');
        if (statusToggle) {
            const uId = statusToggle.getAttribute('data-id');
            const isChecked = statusToggle.checked;
            const newStatus = isChecked ? 1 : 0;
            const row = statusToggle.closest('tr');
            const label = row?.querySelector('.user-status-label');
            const editBtn = row?.querySelector('.edit-tech-btn');

            // Optimistic UI update
            if (label) {
                label.textContent = isChecked ? 'Active' : 'Inactive';
                label.style.color = isChecked ? 'var(--success)' : 'var(--danger)';
            }

            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id', uId);
            formData.append('is_active', newStatus);

            fetch('api/users.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    if (editBtn) editBtn.setAttribute('data-status', newStatus);
                    if (typeof showToast === 'function') {
                        showToast(res.message || `Account marked as ${isChecked ? 'Active' : 'Inactive'}.`, 'success', 'Status Updated');
                    }
                } else {
                    // Revert toggle on error
                    statusToggle.checked = !isChecked;
                    if (label) {
                        label.textContent = !isChecked ? 'Active' : 'Inactive';
                        label.style.color = !isChecked ? 'var(--success)' : 'var(--danger)';
                    }
                    if (typeof showToast === 'function') {
                        showToast(res.message || 'Failed to update account status.', 'error', 'Error');
                    } else {
                        alert(res.message || 'Failed to update account status.');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                statusToggle.checked = !isChecked;
                if (label) {
                    label.textContent = !isChecked ? 'Active' : 'Inactive';
                    label.style.color = !isChecked ? 'var(--success)' : 'var(--danger)';
                }
                if (typeof showToast === 'function') {
                    showToast('Server error while updating status.', 'error', 'Error');
                } else {
                    alert('Server error while updating status.');
                }
            });
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

});
