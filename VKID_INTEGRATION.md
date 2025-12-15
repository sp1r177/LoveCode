# VK ID Integration Setup

## Configuration Steps

1. **Create a VK Application**:
   - Go to https://vk.com/apps?act=manage
   - Create a new application
   - Select "Website" as the platform
   - Set the website address to your domain (e.g., https://flirt-ai.ru)
   - Set the base domain to your domain (e.g., flirt-ai.ru)

2. **Configure VK Application Settings**:
   - In the app settings, set the "Authorized redirect URI" to:
     ```
     https://flirt-ai.ru/api/auth/vk-callback
     ```
   - Save the changes

3. **Update Environment Variables**:

   Backend (.env file):
   ```
   VK_APP_ID=your_vk_app_id
   VK_APP_SECRET=your_vk_app_secret
   VK_REDIRECT_URI=https://flirt-ai.ru/api/auth/vk-callback
   FRONTEND_URL=https://flirt-ai.ru
   ```

   Frontend (.env file):
   ```
   VITE_VK_APP_ID=your_vk_app_id
   VITE_VK_REDIRECT_URI=https://flirt-ai.ru/api/auth/vk-callback
   ```

4. **Deploy Changes**:
   - Rebuild and redeploy both frontend and backend
   - Ensure the VK ID SDK is loading correctly
   - Test the authentication flow

## Troubleshooting

### 403 Forbidden Errors
- Check that VK_APP_ID and VK_APP_SECRET are correct
- Verify that the redirect URI in VK app settings matches VK_REDIRECT_URI
- Ensure FRONTEND_URL is set correctly in backend .env

### Opening in New Tabs
- The VK ID widget should now handle authentication inline
- If it still opens new tabs, check browser extensions that might interfere
- Ensure the VK ID SDK is loaded correctly

### CORS Issues
- Make sure FRONTEND_URL in backend .env matches your actual frontend URL
- Check that the backend is sending proper CORS headers
- Verify that withCredentials is set to true in frontend requests