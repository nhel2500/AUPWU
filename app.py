from flask import Flask, render_template, session, redirect, url_for, request, flash
from flask_sqlalchemy import SQLAlchemy
import os
from datetime import datetime
from werkzeug.security import generate_password_hash, check_password_hash
import logging

# Set up logging
logging.basicConfig(level=logging.DEBUG)

# Initialize Flask app
app = Flask(__name__)
app.secret_key = os.environ.get("SESSION_SECRET", "aupwu_secret_key")

# Configure the database
app.config["SQLALCHEMY_DATABASE_URI"] = os.environ.get("DATABASE_URL")
app.config["SQLALCHEMY_ENGINE_OPTIONS"] = {
    "pool_recycle": 300,
    "pool_pre_ping": True,
}

# Initialize SQLAlchemy
db = SQLAlchemy(app)

# Define models based on the PHP/MySQL schema
class User(db.Model):
    __tablename__ = 'users'
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(50), unique=True, nullable=False)
    password = db.Column(db.String(255), nullable=False)
    email = db.Column(db.String(100), unique=True, nullable=False)
    role = db.Column(db.String(10), nullable=False, default='member')
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    # Relationship
    member = db.relationship('Member', backref='user', uselist=False)
    
    def __init__(self, **kwargs):
        super(User, self).__init__(**kwargs)

class Member(db.Model):
    __tablename__ = 'members'
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    name = db.Column(db.String(100), nullable=False)
    address = db.Column(db.Text, nullable=False)
    unit_college = db.Column(db.String(100), nullable=False)
    designation = db.Column(db.String(100), nullable=False)
    chapter = db.Column(db.String(100), nullable=False)
    date_of_appointment = db.Column(db.Date, nullable=False)
    date_of_birth = db.Column(db.Date, nullable=False)
    contact_number = db.Column(db.String(20), nullable=False)
    email = db.Column(db.String(100), nullable=False)
    is_active = db.Column(db.Boolean, default=True)
    up_status = db.Column(db.String(10), nullable=False, default='in')
    photo_path = db.Column(db.String(255))
    signature_path = db.Column(db.String(255))
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    def __init__(self, **kwargs):
        super(Member, self).__init__(**kwargs)

class Committee(db.Model):
    __tablename__ = 'committees'
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), nullable=False)
    description = db.Column(db.Text)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    def __init__(self, **kwargs):
        super(Committee, self).__init__(**kwargs)

# Routes
@app.route('/')
def index():
    return render_template('index.html')

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        
        if not username or not password:
            flash('Please provide both username and password', 'danger')
            return render_template('login.html')
            
        user = User.query.filter_by(username=username).first()
        
        if user and check_password_hash(user.password, password):
            session['user_id'] = user.id
            session['username'] = user.username
            session['role'] = user.role
            
            flash('Login successful!', 'success')
            
            if user.role == 'admin':
                return redirect(url_for('admin_dashboard'))
            elif user.role == 'officer':
                return redirect(url_for('officer_dashboard'))
            else:
                return redirect(url_for('member_dashboard'))
        else:
            flash('Invalid username or password', 'danger')
    
    return render_template('login.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        email = request.form.get('email')
        password = request.form.get('password')
        
        if not username or not email or not password:
            flash('All fields are required', 'danger')
            return render_template('register.html')
            
        # Check if username or email already exists
        if User.query.filter_by(username=username).first():
            flash('Username already exists', 'danger')
            return render_template('register.html')
        
        if User.query.filter_by(email=email).first():
            flash('Email already exists', 'danger')
            return render_template('register.html')
        
        # Create new user
        hashed_password = generate_password_hash(password)
        new_user = User()
        new_user.username = username
        new_user.email = email
        new_user.password = hashed_password
        new_user.role = 'member'
        
        db.session.add(new_user)
        db.session.commit()
        
        flash('Registration successful! You can now log in.', 'success')
        return redirect(url_for('login'))
    
    return render_template('register.html')

@app.route('/admin/dashboard')
def admin_dashboard():
    if session.get('role') != 'admin':
        flash('Unauthorized access', 'danger')
        return redirect(url_for('login'))
    
    return render_template('admin_dashboard.html')

@app.route('/officer/dashboard')
def officer_dashboard():
    if session.get('role') not in ['admin', 'officer']:
        flash('Unauthorized access', 'danger')
        return redirect(url_for('login'))
    
    return render_template('officer_dashboard.html')

@app.route('/member/dashboard')
def member_dashboard():
    if not session.get('user_id'):
        flash('Please log in to access your dashboard', 'danger')
        return redirect(url_for('login'))
    
    return render_template('member_dashboard.html')

@app.route('/logout')
def logout():
    session.clear()
    flash('You have been logged out', 'info')
    return redirect(url_for('index'))

@app.route('/about')
def about():
    return render_template('about.html')

# Create all database tables
with app.app_context():
    db.create_all()
    
    # Add default admin account if it doesn't exist
    admin = User.query.filter_by(username='admin').first()
    if not admin:
        admin_password = generate_password_hash('secret')
        admin = User()
        admin.username = 'admin'
        admin.email = 'admin@aupwu.org'
        admin.password = admin_password
        admin.role = 'admin'
        db.session.add(admin)
        
        # Add default committees
        committee_list = [
            ('Human Resource Merit Promotion & Selection Board', 'Handles promotions and merit-based selections'),
            ('Human Resource Committee', 'Manages HR policies and practices'),
            ('Janitorial Inspection Monitoring Team Committee', 'Oversees janitorial services and quality'),
            ('Security Committee', 'Handles security matters and policies'),
            ('Sports and Development Committee', 'Organizes sports events and related activities'),
            ('Search Committee', 'Responsible for searching qualified candidates for positions'),
            ('Bids and Awards Committee', 'Handles procurement and bidding processes'),
            ('Gender and Development Committee', 'Promotes gender equality and inclusivity'),
            ('Cultural Committee', 'Organizes cultural events and activities'),
            ('Finance Committee', 'Manages financial affairs and budgeting'),
            ('Education and Training Committee', 'Handles educational programs and training'),
            ('Health and Wellness Committee', 'Promotes health and wellness initiatives'),
            ('Environmental Committee', 'Handles environmental concerns and programs'),
            ('Grievance Committee', 'Addresses complaints and grievances'),
            ('Other', 'For committees not specified in the list')
        ]
        
        for name, description in committee_list:
            committee = Committee.query.filter_by(name=name).first()
            if not committee:
                new_committee = Committee()
                new_committee.name = name
                new_committee.description = description
                db.session.add(new_committee)
        
        db.session.commit()

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)