SafeCedi

SafeCedi is a web-based scam detection system designed to help users in Ghana identify fraudulent mobile-money messages, suspicious SMS messages, WhatsApp forwards, and phishing links.

Users can paste a suspicious message and receive a risk verdict, supported by evidence such as suspicious language patterns, sender information, URLs, and reputation data.

SafeCedi also uses crowd-reported scam information to help identify suspicious messages.

Project Overview

Course: CS341 – Web Technologies
Project: SafeCedi
Technology Stack: PHP, MySQL, jQuery, HTML, CSS, JavaScript
Architecture: No framework

Risk Verdicts

SafeCedi categorises submitted messages into four risk bands:

* Looks Safe
* Unclear
* Suspicious
* Dangerous

Each verdict is accompanied by evidence explaining why the message received its risk classification.

Features

* Analysis of suspicious SMS messages and WhatsApp forwards.
* Detection of suspicious language and scam patterns.
* URL extraction and analysis.
* URL reputation checking.
* Evidence-based risk verdicts.
* Crowd-reported scam information.
* User authentication and role-based access control.
* Reporting and moderation functionality.
* Security protections against common web application attacks.

Running Locally

Requirements

You need:

* PHP 8.1 or later.
* MySQL or MariaDB.
* PHP extensions:
    * pdo_mysql
    * curl
    * mbstring
    * openssl

1. Configuration

Copy the example environment file:

cp .env.example .env

Edit .env and configure your database credentials and other required environment variables.

2. Create the Database

Create the database:

mysql -u root -p -e "CREATE DATABASE safecedi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

Import the database schema:

mysql -u root -p safecedi < database/schema.sql

3. Check the Setup

Run the health check:

php ops/healthcheck.php

4. Start the Development Server

php -S localhost:8000 -t public

Open the application in your browser:

http://localhost:8000

5. Create a Moderator Account

To create a moderator account:

php bin/create-user.php --email=you@example.com --name="Your Name" --role=moderator

Follow the prompts to complete account creation.

Testing

Authentication Smoke Test

With the application running, execute:

bash tests/auth_smoke.sh http://localhost:8000

This runs the authentication smoke tests against the local application.

Additional security, functionality and detection tests are documented in the project’s test plan.

Project Structure

public/        The only directory exposed through the web server
includes/      Bootstrap, configuration, errors, database, security,
               sessions and authentication
templates/     Shared header and footer templates
database/      Database schema and seed data
engine/        Scam detection rule engine and SSRF protection
bin/           Command-line utilities, including user creation
ops/            Operational tools, including health checks
tests/         Smoke tests and detection test corpus
docs/          Deployment guide, authentication guide,
               integration contract, team documentation
storage/logs/  Application logs (excluded from Git)

Documentation

* Contributing Guide – Branch naming, pull requests and commit guidelines.
* Deployment Guide – Hosting, HTTPS, MySQL, environment variables and Safe Browsing configuration.
* Authentication Guide – Registration, login, sessions, roles and authentication explanations.
* Integration Contract – Functions and responsibilities across project components.
* Team Documentation – Team structure and responsibilities.
* Threat Model – Security threats, risks and mitigations identified for SafeCedi.

Security

SafeCedi treats all user-submitted messages, URLs and external responses as untrusted input.

Security measures include prepared database statements, output escaping, CSRF protection, authentication controls, role-based access control and URL validation.

The application also addresses risks including:

* Server-Side Request Forgery (SSRF).
* SQL Injection.
* Cross-Site Scripting (XSS).
* Cross-Site Request Forgery (CSRF).
* Unauthorised access.
* Denial of Service.
* Leaked secrets.
* Session hijacking.
* Privacy risks.
* Information disclosure.
* Open redirects.

Security controls are documented in the project’s threat model and tested through the test plan.

Database Schema

The database schema is defined in:

database/schema.sql

The database contains the tables required to support user accounts, message submissions, reports, scam detection rules, feedback and related application functionality.

A database schema diagram will be included in the final project documentation.

Test Accounts

Test accounts used during project evaluation will be documented here.

Account Type	Username/Email	Password
User	To be provided	To be provided
Moderator	To be provided	To be provided

Note: Test credentials should only be provided for designated evaluation accounts.

Detection Accuracy

The scam detection system is evaluated using a test corpus containing legitimate messages, suspicious messages and scam messages.

The final evaluation results will include:

Metric	Result
Total test messages	To be completed
True Positives	To be completed
False Positives	To be completed
True Negatives	To be completed
False Negatives	To be completed
Precision	To be completed
Recall	To be completed
Accuracy	To be completed

These results will be updated following the completion of testing.

Known Limitations

* Scam detection is not guaranteed to identify every fraudulent message.
* Legitimate messages may occasionally be classified as suspicious.
* Scam techniques and fraudulent URLs may change over time.
* URL reputation checks depend on external services and their availability.
* Detection results should be treated as guidance rather than a guarantee of safety.
* The system’s accuracy depends partly on the quality and coverage of its detection rules and test data.

Contributors

SafeCedi is developed as a group project for CS341 – Web Technologies.
