<?php
/**
 * Global Footer & Scripts Component
 * Can be reused across any portal page.
 *
 * Variables you can define before including:
 *   $extra_js (array) - Optional additional JS file paths
 */
?>
        <!-- Dashboard Footer -->
        <footer class="dashboard-footer">
            <div>© 2026 Asset IT Helpdesk Portal. All rights reserved.</div>
            <div>
                Design and Maintain by <a href="https://virosentrepreneurs.com" target="_blank">Viros</a>
            </div>
        </footer>

    </div> <!-- End .main-wrapper -->

</div> <!-- End .app-container -->

<?php if (!isset($active_page) || strpos($active_page, 'employee_') !== 0): ?>
<!-- ==================== QUICK TICKET MODAL ==================== -->
<div class="modal-overlay" id="ticketModal" style="display: none;">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Create New Service Desk Ticket</h3>
            <button class="modal-close-btn" id="closeTicketModalBtn">&times;</button>
        </div>
        <form id="newTicketForm">
            <div class="modal-body">
                <div class="modal-form-group">
                    <label for="ticketTitle">Issue Subject *</label>
                    <input type="text" id="ticketTitle" placeholder="e.g. Laptop screen flickering" required>
                </div>

                <div class="modal-form-group">
                    <label for="ticketCategory">Category *</label>
                    <select id="ticketCategory" required>
                        <option value="">Select Category</option>
                        <option value="Hardware Allocation">Hardware Allocation</option>
                        <option value="Laptop / Desktop Repair">Laptop / Desktop Repair</option>
                        <option value="Network & VPN">Network & VPN</option>
                        <option value="Software & OS">Software & OS</option>
                        <option value="Email & Accounts">Email & Accounts</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="ticketPriority">Priority</label>
                    <select id="ticketPriority">
                        <option value="Medium">Medium</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Low">Low</option>
                    </select>
                </div>

                <div class="modal-form-group">
                    <label for="ticketUser">User / Employee Name *</label>
                    <input type="text" id="ticketUser" placeholder="e.g. Priya Sharma" required>
                </div>

                <div class="modal-form-group">
                    <label for="ticketDesc">Description</label>
                    <textarea id="ticketDesc" rows="3" placeholder="Provide details about the issue..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelTicketModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Create Ticket</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Core Scripts -->
<script src="js/toast.js?v=<?php echo file_exists(__DIR__ . '/../js/toast.js') ? filemtime(__DIR__ . '/../js/toast.js') : time(); ?>"></script>
<script src="js/dashboard.js?v=<?php echo file_exists(__DIR__ . '/../js/dashboard.js') ? filemtime(__DIR__ . '/../js/dashboard.js') : time(); ?>"></script>
<script src="js/searchable-select.js?v=<?php echo file_exists(__DIR__ . '/../js/searchable-select.js') ? filemtime(__DIR__ . '/../js/searchable-select.js') : time(); ?>"></script>

<?php if (isset($extra_js) && is_array($extra_js)): ?>
    <?php foreach ($extra_js as $js_file): ?>
        <?php $file_ver = file_exists(__DIR__ . '/../' . $js_file) ? filemtime(__DIR__ . '/../' . $js_file) : time(); ?>
        <script src="<?php echo htmlspecialchars($js_file . '?v=' . $file_ver); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
