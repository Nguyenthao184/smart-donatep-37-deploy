# Railway Deployment Guide - SmartDonate

## Yêu cầu
- Railway Account (https://railway.app)
- Railway CLI (hoặc sử dụng Web UI)

## Cách Deploy

### Option 1: Dùng Railway UI (Dễ nhất)

1. Đăng nhập vào https://railway.app
2. Click **+ New Project**
3. Chọn **Deploy from GitHub**
4. Kết nối repo GitHub của bạn
5. Railway sẽ tự detect và build

### Option 2: Dùng Railway CLI

```bash
npm install -g @railway/cli
railway login
cd d:\SmartDonate
railway link          # Link với project trên Railway
railway up            # Deploy
```

## Cấu hình Environment Variables trên Railway

Sau khi tạo project, tạo các service:

### 1. PostgreSQL Database (Khuyến nghị hơn MySQL)
- Click **+ Add Service** → **PostgreSQL**
- Railway sẽ tự set biến `DATABASE_URL`

### 2. Cấu hình PHP Backend
Thêm Environment Variables:
```
APP_KEY=base64:Ry91YaMyXk27Hn5GKMCN3l5SPlqRRqKnx33+8QAFj34=
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.railway.app

CLOUDINARY_CLOUD_NAME=digksjpkd
CLOUDINARY_API_KEY=876351292979841
CLOUDINARY_API_SECRET=kTjSyJ8GEwLudyys8VJuEWlb-84

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap1
FILESYSTEM_DISK=cloudinary
QUEUE_CONNECTION=database
CACHE_STORE=database
VITE_PUSHER_APP_KEY=your_app_key
VITE_PUSHER_APP_CLUSTER=ap1
```

### 3. Cấu hình Frontend (Optional)
Để deploy frontend riêng:
- Tạo **Static Site** service
- Build command: `npm run build --prefix frontend`
- Publish directory: `frontend/dist`

## Cấu hình Files

Tôi đã tạo các file sau:
- ✅ `Dockerfile` - Docker image config cho backend
- ✅ `railway.json` - Railway build config
- ✅ `backend/Procfile` - Heroku buildpack config
- ✅ `.dockerignore` - Giảm kích thước image
- ✅ `package.json` (root) - Node dependencies

## Lưu ý Quan trọng

1. **Database:**
   - Railway MySQL/PostgreSQL có ephemeral storage
   - Hãy backup database định kỳ
   - Migrations sẽ tự chạy qua `release: php artisan migrate --force`

2. **File Storage:**
   - Tất cả files upload PHẢI vào **Cloudinary** (không persistent local storage)
   - ✅ Bạn đã config Cloudinary rồi

3. **Environment:**
   - `APP_ENV=production` bắt buộc trên Railway
   - `APP_DEBUG=false` để bảo mật
   - Sử dụng biến môi trường cho tất cả secrets

4. **Domain:**
   - Railway tự cấp domain: `your-app.railway.app`
   - Hoặc connect custom domain qua Railway dashboard

## Troubleshooting

### Lỗi "Railpack could not determine how to build the app"
- ✅ Đã fix bằng `railway.json`

### Lỗi Database Connection
- Check DATABASE_URL hoặc DB_HOST trong Railway environment
- Chạy: `railway run php artisan migrate`

### Lỗi Permission khi upload
- Đảm bảo tất cả upload dùng Cloudinary (đã config ✅)

### Build quá lâu hoặc fail
- Giảm kích thước bằng `.dockerignore`
- Kiểm tra log: `railway logs`

## Command Útil

```bash
# View logs
railway logs

# Run artisan command
railway run php artisan tinker

# SSH vào container
railway shell

# Check environment
railway env
```

## Phần tiếp theo

Sau khi deploy thành công:
1. Test upload ảnh → check Cloudinary
2. Test database operations
3. Test frontend (nếu deploy riêng)
