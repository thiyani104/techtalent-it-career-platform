# TechTalent – Intelligent IT Career Development & Recruitment Platform

TechTalent is a web-based career development and recruitment platform designed to help IT job seekers explore opportunities, prepare CVs, and understand how their skills align with job requirements. It also provides tools for employers to manage vacancies and applicants, alongside administrative features.

> **Project type:** Academic Software Engineering Project  
> **Status:** Academic project / local development version

## Key Features

### For Job Seekers
- Create and manage a professional profile and technical skills.
- Browse IT vacancies and view job details.
- Create, import, edit, and manage CVs through CV Studio.
- Check CV content using rule-based ATS-readiness checks.
- Compare candidate skills with job requirements.
- View job-match scores and skill-gap recommendations.
- Apply for vacancies and track application progress.

### For Employers
- Manage an employer profile.
- Create and manage job vacancies.
- View applicants and their application information.
- Review applicant CV snapshots associated with vacancies.

### For Administrators
- View the administration dashboard.
- Manage vacancies, skills, reviews, and contact inquiries.

## CV Analysis and Skill Matching

TechTalent includes automated CV analysis and skill-matching functionality implemented in PHP.

The current implementation uses **rule-based checks and matching logic**, rather than a trained machine-learning model.

The process includes:
1. Extracting readable text from supported CV files.
2. Checking CV content against predefined ATS-related rules.
3. Processing and normalising skill names.
4. Comparing candidate skills with the required skills for a vacancy.
5. Calculating a job-match percentage and identifying missing skills.
6. Generating recommendations based on the identified skill gaps.

The analysis is intended to provide guidance to job seekers. It is not an official ATS certification and does not guarantee employment. Scanned image-only PDFs and some unusual document layouts may not be fully supported.

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP | Server-side application logic and CV analysis |
| MySQL | Relational database |
| PDO | PHP database connectivity |
| HTML5 and CSS3 | Page structure and styling |
| JavaScript | Client-side interactivity |
| Bootstrap | Responsive interface components |
| WAMP | Local Apache, PHP, and MySQL development environment |
| PHPMailer | Email functionality |

## Project Structure

The application source code is located in the `techtalent/` directory.

```text
.
└── techtalent/
    ├── admin/          # Administrator pages
    ├── ai/             # CV checks, parsing, and skill matching
    ├── assets/         # CSS, JavaScript, and images
    ├── auth/            # Registration and authentication
    ├── config/          # Database and mail configuration
    ├── database/        # SQL schema and migrations
    ├── employer/        # Employer features
    ├── guest/           # Public pages and vacancy browsing
    ├── includes/        # Shared functions and interface components
    ├── jobseeker/       # Job seeker and CV Studio features
    ├── storage/         # Private application file storage
    └── uploads/         # Uploaded assets such as employer logos
```

## Local Installation

The following instructions are for running the project locally using WAMP.

### 1. Install the requirements
- Install WAMP Server or another local PHP and MySQL environment.
- Start Apache and MySQL.
- Use a PHP version compatible with the project.
- Ensure the required PHP extensions are enabled, including `pdo_mysql`, `fileinfo`, and `zlib`.

### 2. Copy the project
Copy the `techtalent` application folder into your web server's document root.

For WAMP, this is commonly:

```text
C:\wamp64\www\techtalent
```

### 3. Create the database
1. Open phpMyAdmin.
2. Create a database named `techtalent_db`.
3. Select the database.
4. Import the main SQL file:

```text
techtalent/database/techtalent.sql
```

5. Run the CV Studio and contact inquiry migrations in the same database:

```text
techtalent/database/migrations/001_cv_studio.sql
techtalent/database/migrations/002_contact_inquiries.sql
```

Run each SQL file once, in the order shown.

### 4. Configure the database connection
Open:

```text
techtalent/config/db.php
```

Update the host, database name, username, and password to match your local MySQL configuration.

Do not use development credentials on a public server.

### 5. Configure email (optional)
Email features require valid SMTP settings. Use the example configuration as a guide:

```text
techtalent/config/mail_settings.example.php
```

Create or configure your own local `mail_settings.php` with your SMTP details. Never commit real SMTP credentials, app passwords, or other secrets to GitHub.

### 6. Open the application
With Apache and MySQL running, visit:

```text
http://localhost/techtalent/
```

If your local folder or virtual-host configuration is different, adjust the URL accordingly.

## Security and Privacy

- Do not upload real CVs, personal information, passwords, SMTP credentials, API keys, or private configuration files to a public repository.
- Keep private uploads and local configuration out of version control.
- Use fictional test data when demonstrating the application.
- Configure production security settings before deploying the application to a public server.

## Current Scope and Limitations

- CV analysis and skill matching are rule-based; the project does not currently use a trained machine-learning model.
- Match scores depend on the skills entered in the candidate CV and vacancy requirements.
- Scanned image-only CVs may not be readable by the current text-extraction process.
- Results are guidance for job seekers and should not be treated as a hiring decision.

## Future Improvements

- Explore natural language processing for more advanced CV and job-description understanding.
- Improve skill recognition and personalised learning recommendations.
- Add further career-development and professional networking features.
- Improve deployment, testing, and accessibility.

## Academic Project

TechTalent was developed as an academic Software Engineering project to apply practical skills in web development, database design, backend programming, and automated CV and skill analysis.

---

**Note:** This repository contains an academic project. Review configuration files, uploaded files, and test data before making the repository public.
