import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import Home from './pages/Home'
import Analyze from './pages/Analyze'
import Pricing from './pages/Pricing'
import Profile from './pages/Profile'
import AuthCallback from './pages/AuthCallback'
import PaymentSuccess from './pages/PaymentSuccess'
import Privacy from './pages/Privacy'
import Terms from './pages/Terms'
import PrivateRoute from './components/PrivateRoute'
import Layout from './components/Layout'

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Layout />}>
            <Route index element={<Home />} />
            <Route path="auth/callback" element={<AuthCallback />} />
            <Route path="payment/success" element={<PaymentSuccess />} />
            <Route path="privacy" element={<Privacy />} />
            <Route path="terms" element={<Terms />} />
            <Route
              path="analyze"
              element={
                <PrivateRoute>
                  <Analyze />
                </PrivateRoute>
              }
            />
            <Route
              path="pricing"
              element={<Pricing />}
            />
            <Route
              path="profile"
              element={
                <PrivateRoute>
                  <Profile />
                </PrivateRoute>
              }
            />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}

export default App

