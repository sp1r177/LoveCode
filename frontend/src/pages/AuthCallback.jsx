import { useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

export default function AuthCallback() {
  const [searchParams] = useSearchParams()
  const { login } = useAuth()
  const navigate = useNavigate()

  useEffect(() => {
    const token = searchParams.get('token')
    if (token) {
      login(token)
      navigate('/analyze')
    } else {
      navigate('/')
    }
  }, [])

  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-gray-500">Авторизация...</div>
    </div>
  )
}

