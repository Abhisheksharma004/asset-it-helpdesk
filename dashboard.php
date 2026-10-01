<?php
// Asset Management & IT Service Desk Portal - Dashboard Page
$page_title = "Dashboard - Asset Management & IT Service Desk";
$active_page = "dashboard";

// Include Modular Components
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Dashboard Body Content -->
<main class="dashboard-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h1>Asset & Service Desk Operations</h1>
            <p>Welcome back! Here is what's happening across company assets and helpdesk tickets today.</p>
        </div>
        <div class="welcome-stats">
            <div class="welcome-badge">
                <div class="num">99.8%</div>
                <div class="lbl">IT Uptime</div>
            </div>
            <div class="welcome-badge">
                <div class="num">18 min</div>
                <div class="lbl">Avg Response</div>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="metrics-grid">
        
        <!-- Metric 1: Total Assets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Total Managed Assets</span>
                <div class="metric-icon-wrap cyan">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value">1,284</div>
            <div class="metric-footer">
                <span class="trend-up">↑ +14%</span>
                <span>vs last month (98% active)</span>
            </div>
        </div>

        <!-- Metric 2: Open Tickets -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Active Service Tickets</span>
                <div class="metric-icon-wrap warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
            </div>
            <div class="metric-value">48</div>
            <div class="metric-footer">
                <span class="trend-neutral">12 Urgent</span>
                <span>• 36 Standard priority</span>
            </div>
        </div>

        <!-- Metric 3: Pending Allocation -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Stock & Ready to Assign</span>
                <div class="metric-icon-wrap navy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
            </div>
            <div class="metric-value">96</div>
            <div class="metric-footer">
                <span class="trend-up">Available</span>
                <span>Laptops, Monitors & Kits</span>
            </div>
        </div>

        <!-- Metric 4: Resolved Today -->
        <div class="metric-card">
            <div class="metric-top">
                <span class="metric-title">Resolved Tickets Today</span>
                <div class="metric-icon-wrap success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
            </div>
            <div class="metric-value">31</div>
            <div class="metric-footer">
                <span class="trend-up">↑ 96.5%</span>
                <span>SLA compliance rate</span>
            </div>
        </div>

    </div>

    <!-- Dashboard Main Grid: Table & Widgets -->
    <div class="dashboard-grid">

        <!-- Left Column: Recent Support Tickets Table -->
        <div class="content-card">
            <div class="card-header">
                <h2>Recent IT Support Tickets</h2>
                <div class="card-header-actions">
                    <button class="card-btn" onclick="showToast('Exporting tickets to CSV...', 'info')">Export CSV</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Issue / Asset</th>
                            <th>Requested By</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="ticket-id">#TK-1082</span></td>
                            <td>
                                <span class="ticket-subject">VPN Connectivity Failure on Mac</span>
                                <span class="ticket-category">MacBook Pro M2 • IT Infrastructure</span>
                            </td>
                            <td>Rohit Sharma</td>
                            <td><span class="badge badge-urgent">Urgent</span></td>
                            <td><span class="badge badge-progress">In Progress</span></td>
                            <td>
                                <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket #TK-1082 opened', 'info')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td><span class="ticket-id">#TK-1081</span></td>
                            <td>
                                <span class="ticket-subject">New Dell Latitude Laptop Allocation</span>
                                <span class="ticket-category">Hardware Allocation • HR Dept</span>
                            </td>
                            <td>Neha Verma</td>
                            <td><span class="badge badge-medium">Medium</span></td>
                            <td><span class="badge badge-open">Open</span></td>
                            <td>
                                <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket #TK-1081 opened', 'info')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td><span class="ticket-id">#TK-1080</span></td>
                            <td>
                                <span class="ticket-subject">Microsoft 365 License Activation</span>
                                <span class="ticket-category">Software License • Finance Dept</span>
                            </td>
                            <td>Pooja Agarwal</td>
                            <td><span class="badge badge-medium">Medium</span></td>
                            <td><span class="badge badge-progress">In Progress</span></td>
                            <td>
                                <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket #TK-1080 opened', 'info')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td><span class="ticket-id">#TK-1079</span></td>
                            <td>
                                <span class="ticket-subject">Office 2nd Floor Printer Offline</span>
                                <span class="ticket-category">HP LaserJet M404 • Peripherals</span>
                            </td>
                            <td>Amit Kumar</td>
                            <td><span class="badge badge-urgent">Urgent</span></td>
                            <td><span class="badge badge-resolved">Resolved</span></td>
                            <td>
                                <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket #TK-1079 opened', 'info')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr>
                            <td><span class="ticket-id">#TK-1078</span></td>
                            <td>
                                <span class="ticket-subject">RAM Upgrade Request (16GB to 32GB)</span>
                                <span class="ticket-category">Dev Workstation • Engineering</span>
                            </td>
                            <td>Vikram Joshi</td>
                            <td><span class="badge badge-medium">Low</span></td>
                            <td><span class="badge badge-resolved">Resolved</span></td>
                            <td>
                                <button class="action-btn-sm" title="View Ticket" onclick="showToast('Ticket #TK-1078 opened', 'info')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column: Asset Inventory Distribution & Activity -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Asset Distribution Widget -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Asset Categories</h2>
                </div>
                <div class="asset-category-list">
                    <div class="category-row">
                        <div class="category-meta">
                            <span>Laptops & Notebooks</span>
                            <strong>620 Units (72%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-cyan" style="width: 72%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Workstations & Desktops</span>
                            <strong>340 Units (24%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-navy" style="width: 24%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Servers & Network Switches</span>
                            <strong>184 Units (15%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-amber" style="width: 15%;"></div>
                        </div>
                    </div>

                    <div class="category-row">
                        <div class="category-meta">
                            <span>Peripherals & Displays</span>
                            <strong>140 Units (11%)</strong>
                        </div>
                        <div class="category-progress">
                            <div class="progress-fill progress-green" style="width: 11%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Feed Widget -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Activity</h2>
                </div>
                <div class="activity-feed">
                    <div class="activity-item">
                        <div class="activity-dot">💻</div>
                        <div class="activity-body">
                            <div class="activity-text"><strong>Dell Latitude 5430</strong> assigned to <strong>Rahul Verma</strong></div>
                            <div class="activity-time">12 minutes ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-dot">🔧</div>
                        <div class="activity-body">
                            <div class="activity-text"><strong>Firewall AMC</strong> renewal completed successfully</div>
                            <div class="activity-time">1 hour ago</div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-dot">🎫</div>
                        <div class="activity-body">
                            <div class="activity-text">Ticket <strong>#TK-1079</strong> closed by IT Support</div>
                            <div class="activity-time">3 hours ago</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</main>

<?php
// Include Modular Footer & Modals
include 'includes/footer.php';
?>
