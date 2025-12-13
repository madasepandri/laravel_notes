# GEMINI.md

## Project Overview

This is a Laravel project called "Laravel Notes". It's a simple note-taking application that allows users to create, view, edit, and delete notes. The application uses MySQL for the database and includes features like user authentication, search, and pagination.

## Building and Running

### Setup
To set up the project, run the following command:
```bash
composer setup
```
This will install the dependencies, create the `.env` file, generate the application key, run the database migrations, and build the frontend assets.

### Running the application
To run the application, use the following command:
```bash
composer dev
```
This will start the development server, queue listener, log watcher, and Vite dev server.

### Testing
To run the tests, use the following command:
```bash
composer test
```

## Development Conventions

The project follows the standard Laravel conventions.
- Controllers are used to handle the application logic.
- Models are used to interact with the database.
- Views are used to render the HTML.
- Routes are defined in the `routes/web.php` file.
- The project uses Blade templating for the views.
- The project uses Tailwind CSS for styling.
