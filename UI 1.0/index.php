<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeCedi | Think Before You Pay</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main>

    <section class="hero">
        <div class="hero-content">

            <div class="hero-badge">
                <span class="badge-dot"></span>
                Digital Safety for Ghana
            </div>

            <h1>
                Think before<br>
                <span>you pay.</span>
            </h1>

            <p class="hero-description">
                Protect yourself from digital scams.
                Check suspicious messages, phone numbers
                and links before making your next move.
            </p>

            <div class="hero-buttons">
                <a href="scan.php" class="primary-btn">
                    Check for scams
                    <span>→</span>
                </a>

                <a href="report.php" class="secondary-btn">
                    Report a scam
                </a>
            </div>

            <div class="hero-trust">
                <div class="trust-icon">✓</div>
                <span>Stay informed. Stay protected.</span>
            </div>

        </div>

        <div class="hero-visual">

            <div class="security-card">

                <div class="security-card-header">
                    <div class="security-icon">✦</div>

                    <div>
                        <p class="card-label">SafeCedi Security</p>
                        <p class="card-status">Protection preview</p>
                    </div>

                    <div class="status-dot"></div>
                </div>

                <div class="scan-visual">
                    <div class="scan-circle">
                        <div class="scan-inner">
                            <span class="shield-symbol">✓</span>
                        </div>
                    </div>
                </div>

                <div class="security-card-footer">
                    <span>Digital protection</span>
                    <span class="footer-status">Active</span>
                </div>

            </div>

            <div class="floating-card floating-card-top">
                <span class="floating-icon">🔒</span>
                <div>
                    <strong>Secure analysis</strong>
                    <small>Suspicious content detection</small>
                </div>
            </div>

            <div class="floating-card floating-card-bottom">
                <span class="floating-icon warning-icon">!</span>
                <div>
                    <strong>Stay alert</strong>
                    <small>Verify before sending money</small>
                </div>
            </div>

        </div>
    </section>


    <section class="tools-section">

        <div class="section-heading">
            <span class="section-label">YOUR DIGITAL SAFETY TOOLS</span>

            <h2>One platform.<br>Multiple ways to stay safe.</h2>

            <p>
                Identify suspicious activity before it becomes
                a financial loss.
            </p>
        </div>

        <div class="tools-grid">

            <a href="scan.php" class="tool-card">

                <div class="tool-icon message-icon">✉</div>

                <h3>Message Scanner</h3>

                <p>
                    Analyse suspicious SMS messages and payment
                    requests for potential scam indicators.
                </p>

                <span class="tool-link">Scan a message →</span>

            </a>


            <a href="scan.php?type=phone" class="tool-card">

                <div class="tool-icon phone-icon">⌕</div>

                <h3>Phone Number Check</h3>

                <p>
                    Check whether a phone number has been
                    associated with community scam reports.
                </p>

                <span class="tool-link">Check a number →</span>

            </a>


            <a href="scan.php?type=url" class="tool-card">

                <div class="tool-icon link-icon">↗</div>

                <h3>Link Checker</h3>

                <p>
                    Inspect suspicious links for potentially
                    deceptive or dangerous characteristics.
                </p>

                <span class="tool-link">Check a link →</span>

            </a>

        </div>

    </section>


    <section class="how-section">

        <div class="section-heading">
            <span class="section-label">HOW IT WORKS</span>
            <h2>Safety in three simple steps.</h2>
        </div>

        <div class="steps-grid">

            <div class="step-card">
                <span class="step-number">01</span>
                <h3>Submit</h3>
                <p>
                    Paste a suspicious message, link or phone number
                    into SafeCedi.
                </p>
            </div>

            <div class="step-card">
                <span class="step-number">02</span>
                <h3>Analyse</h3>
                <p>
                    Our detection engine checks for suspicious
                    indicators and known reported patterns.
                </p>
            </div>

            <div class="step-card">
                <span class="step-number">03</span>
                <h3>Stay informed</h3>
                <p>
                    Review the risk assessment and learn
                    what precautions you should take.
                </p>
            </div>

        </div>

    </section>


    <section class="awareness-section">

        <div class="awareness-content">

            <span class="section-label">SCAM AWARENESS</span>

            <h2>Pause. Verify.<br>Protect your money.</h2>

            <p>
                Scammers often use urgency, impersonation and
                promises of rewards to pressure people into
                making financial decisions.
            </p>

            <a href="report.php" class="primary-btn">
                Help protect the community →
            </a>

        </div>

        <div class="awareness-list">

            <div class="awareness-item">
                <span class="awareness-number">01</span>
                <div>
                    <h3>Never share your PIN or OTP</h3>
                    <p>Keep your account credentials private.</p>
                </div>
            </div>

            <div class="awareness-item">
                <span class="awareness-number">02</span>
                <div>
                    <h3>Verify unexpected requests</h3>
                    <p>Contact the organisation through official channels.</p>
                </div>
            </div>

            <div class="awareness-item">
                <span class="awareness-number">03</span>
                <div>
                    <h3>Don't rush financial decisions</h3>
                    <p>Pressure and urgency can be warning signs.</p>
                </div>
            </div>

        </div>

    </section>

</main>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/main.js"></script>

</body>
</html>