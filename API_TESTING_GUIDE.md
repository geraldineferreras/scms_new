# 🧪 API Testing Guide for Postman

## 📋 **Quick Test Setup**

### **1. Local Testing (Recommended)**
```bash
# Start local server
php -S localhost:8080 -t . router.php
```

### **2. Base URL**
- **Local**: `http://localhost:8080`
- **Railway**: `https://your-app-name.railway.app`

---

## 🔐 **Authentication Tests**

### **1. Register User**
```http
POST /api/register
Content-Type: application/json

{
  "username": "testuser",
  "email": "test@example.com",
  "password": "password123",
  "role": "student"
}
```

### **2. Login**
```http
POST /api/login
Content-Type: application/json

{
  "username": "testuser",
  "password": "password123"
}
```

### **3. Validate Token**
```http
GET /api/validate-token
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 👥 **User Management Tests**

### **1. Get All Users**
```http
GET /api/users
Authorization: Bearer YOUR_JWT_TOKEN
```

### **2. Get Current User**
```http
GET /api/user
Authorization: Bearer YOUR_JWT_TOKEN
```

### **3. Update User**
```http
PUT /api/user
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
  "email": "updated@example.com",
  "first_name": "Updated",
  "last_name": "User"
}
```

---

## 👨‍💼 **Admin Endpoints**

### **1. Get Sections**
```http
GET /api/admin/sections
Authorization: Bearer YOUR_JWT_TOKEN
```

### **2. Create Section**
```http
POST /api/admin/sections
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
  "section_name": "Test Section",
  "program": "BSIT",
  "year_level": "1st Year",
  "semester": "1st Semester",
  "academic_year": "2024-2025"
}
```

### **3. Get Classes**
```http
GET /api/admin/classes
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 👨‍🎓 **Student Endpoints**

### **1. Get Grades**
```http
GET /api/student/grades
Authorization: Bearer YOUR_JWT_TOKEN
```

### **2. Join Class**
```http
POST /api/student/join-class
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json

{
  "class_id": 1
}
```

### **3. Get My Classes**
```http
GET /api/student/my-classes
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## 🧪 **Test Endpoints (No Auth Required)**

### **1. Test Controller**
```http
GET /api/test
```

### **2. Upload Test**
```http
GET /api/upload_test
```

---

## 📝 **Postman Collection Setup**

### **1. Environment Variables**
Create a new environment with these variables:
- `base_url`: `http://localhost:8080` (or your Railway URL)
- `jwt_token`: (will be set after login)

### **2. Pre-request Script for Login**
```javascript
// After successful login, set the token
pm.environment.set("jwt_token", pm.response.json().token);
```

### **3. Authorization Header**
For authenticated requests, use:
```
Authorization: Bearer {{jwt_token}}
```

---

## ✅ **Testing Checklist**

- [ ] **Local server running** on port 8080
- [ ] **Register endpoint** working
- [ ] **Login endpoint** working  
- [ ] **JWT token** being received
- [ ] **Authenticated endpoints** working with token
- [ ] **Admin endpoints** accessible with admin role
- [ ] **Student endpoints** accessible with student role
- [ ] **Error handling** working properly
- [ ] **CORS headers** present in responses

---

## 🚨 **Common Issues & Solutions**

### **1. CORS Errors**
- Check if CORS headers are present in responses
- Verify `.htaccess` is working properly

### **2. 404 Errors**
- Ensure routes are properly defined in `application/config/routes.php`
- Check if controller files exist and are named correctly

### **3. Authentication Errors**
- Verify JWT token format: `Bearer YOUR_TOKEN`
- Check if token is expired
- Ensure user has proper role permissions

### **4. Database Connection**
- Verify database configuration in `application/config/database.php`
- Check if database is accessible from Railway

---

## 🚀 **Railway Deployment Testing**

After deploying to Railway:

1. **Update base URL** to your Railway app URL
2. **Test all endpoints** with Railway URL
3. **Verify database connection** works on Railway
4. **Check file uploads** work with Railway storage
5. **Test with real data** to ensure full functionality

---

## 📊 **Expected Response Format**

All API responses should follow this format:
```json
{
  "status": true/false,
  "message": "Success/Error message",
  "data": {...},
  "timestamp": "2024-01-01 12:00:00"
}
``` 