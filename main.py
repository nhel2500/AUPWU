from flask import Flask, request, send_from_directory, render_template_string
import os
import subprocess
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)

@app.route('/')
def index():
    # Create a simple welcome page
    return render_template_string('''
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AUPWU Management System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h1 class="h3 mb-0">Welcome to AUPWU Management System</h1>
                        </div>
                        <div class="card-body">
                            <h2 class="h4">About AUPWU</h2>
                            <p>The All UP Workers Union (AUPWU) is dedicated to serving the public, promoting workers' rights, and ensuring fair workplace treatment.</p>
                            
                            <div class="alert alert-info">
                                <strong>Note:</strong> This is a demo version of the AUPWU Management System running in a Replit environment.
                            </div>
                            
                            <h3 class="h5 mt-4">Features</h3>
                            <ul>
                                <li>Membership management</li>
                                <li>Committee assignments across 15 different committees</li>
                                <li>Officer election system</li>
                                <li>Reports and analytics</li>
                            </ul>
                            
                            <div class="text-center mt-4">
                                <p>Please select an option:</p>
                                <div class="d-grid gap-2 col-md-6 mx-auto">
                                    <a href="/auth/login.php" class="btn btn-primary">Login</a>
                                    <a href="/auth/register.php" class="btn btn-outline-primary">Register</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <p class="mb-0 small">Contact: 89818500 | Address: Magsaysay Ave. near corner Ylanan St., Diliman, Quezon City</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    ''')

@app.route('/<path:path>')
def serve_file(path):
    return send_from_directory('.', path)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)