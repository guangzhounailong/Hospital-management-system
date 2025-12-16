# Hospital Management System

A modern, responsive hospital management system built with HTML, Tailwind CSS, and Alpine.js.

## Project Structure

```
hospital-management-system/
├── index.html          # Main entry point (redirects to login)
├── login.html          # Login page with role-based authentication
├── admin.html          # Administrator dashboard
├── doctor.html         # Doctor dashboard
├── patient.html        # Patient portal
└── styles.css          # Global styles
```

## Features

### 🔐 Authentication
- **Role-based login system**
- Three user roles: Admin, Doctor, Patient
- Secure credential validation

### 👨‍💼 Administrator Features
- **Dashboard**: System overview with statistics
- **Patient Management**: Add, view, edit, delete patients
- **Access to all system modules**

### 👨‍⚕️ Doctor Features
- **Today's Appointments**: View and manage daily schedule
- **Create Medical Records**: Write diagnoses and treatments
- **Prescription Management**: Add medicines with dosages
- **Patient Records**: Access patient histories

### 👤 Patient Features
- **Book Appointments**: Schedule visits with preferred doctors
- **My Appointments**: View, modify, and cancel appointments
- **Medical Records**: Access complete medical history
- **Prescriptions**: View medication details

## Demo Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Doctor | doctor1 | doctor123 |
| Patient | patient1 | patient123 |

## API Specifications

All backend interactions are marked with Chinese TODO comments:

```javascript
// TODO: POST /api/auth/login 登录接口，body: {username, password}
// TODO: GET /api/patients 获取患者列表
// TODO: POST /api/appointments 创建预约，body: {patient_id, doctor_id, appointment_date, time, symptom}
// TODO: POST /api/medical-records 开具病历，body: {patient_id, doctor_id, diagnosis, treatment_plan, medical_image?}
// TODO: POST /api/prescriptions 开具处方，body: {record_id, medicines: [{medicine_id, dosage, dosage_statement, validity_period}]}
```

## Technology Stack

- **Frontend**: HTML5, Tailwind CSS, Alpine.js
- **Styling**: Tailwind CSS with custom components
- **Interactivity**: Alpine.js for reactive UI
- **Storage**: LocalStorage for session management

## Getting Started

1. **Clone the repository**
2. **Open `index.html` in your browser**
3. **Use demo credentials to login**
4. **Explore different role functionalities**

## Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Development

Each role has its own dedicated HTML file for easier maintenance and feature development:

- Modify `login.html` for authentication changes
- Update `admin.html` for administrator features
- Edit `doctor.html` for doctor-specific functionality
- Change `patient.html` for patient portal features

## File Organization Benefits

✅ **Easier Maintenance**: Each role's features are isolated
✅ **Faster Development**: Work on specific role features without affecting others
✅ **Better Collaboration**: Multiple developers can work on different files
✅ **Cleaner Code**: Reduced file size and complexity per file
✅ **Role-based Security**: Clear separation of concerns

---

Built with ❤️ for modern healthcare management
