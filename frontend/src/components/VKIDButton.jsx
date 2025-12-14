import { useEffect, useRef, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import axios from 'axios'
import { getApiUrl } from '../utils/api'

export default function VKIDButton({ className = '' }) {
  const containerRef = useRef(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [initialized, setInitialized] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()
  const API_URL = getApiUrl()

  const initializeVKID = useCallback(() => {
    if (!window.VKIDSDK || !containerRef.current || initialized) {
      return
    }

    const VKID = window.VKIDSDK

    try {
      // Получаем app_id из переменных окружения
      const appId = import.meta.env.VITE_VK_APP_ID || '54395556'
      
      // Для VK ID SDK redirectUrl должен быть URL на вашем домене
      // Должен совпадать с настройками в VK приложении
      // Используем текущий домен или значение из переменных окружения
      const frontendUrl = import.meta.env.VITE_FRONTEND_URL || window.location.origin
      
      // Для VK ID redirectUrl должен совпадать с настройками в VK приложении
      // Обычно это бэкенд URL, указанный в настройках приложения VK
      let redirectUrl = import.meta.env.VITE_VK_REDIRECT_URI
      if (!redirectUrl) {
        // Пробуем использовать бэкенд URL (стандартный вариант)
        redirectUrl = `${frontendUrl}/api/auth/vk-callback`
        // Альтернативный вариант - фронтенд URL:
        // redirectUrl = `${frontendUrl}/auth/vk-callback`
      }

      console.log('Initializing VK ID with:', { appId, redirectUrl })

      VKID.Config.init({
        app: parseInt(appId),
        redirectUrl: redirectUrl,
        responseMode: VKID.ConfigResponseMode.Callback,
        source: VKID.ConfigSource.LOWCODE,
        scope: 'email',
      })

      const oAuth = new VKID.OAuthList()

      oAuth
        .render({
          container: containerRef.current,
          oauthList: ['vkid'],
          scheme: VKID.Scheme.PRIMARY, // Синяя тема
        })
        .on(VKID.WidgetEvents.ERROR, (error) => {
          console.error('VK ID widget error:', error)
          setError('Ошибка авторизации VK ID. Проверьте консоль для деталей.')
          setLoading(false)
        })
        .on(VKID.OAuthListInternalEvents.LOGIN_SUCCESS, async function (payload) {
          console.log('VK ID LOGIN_SUCCESS:', payload)
          const code = payload.code
          const deviceId = payload.device_id

          if (!code || !deviceId) {
            setError('Не получены необходимые данные от VK ID')
            setLoading(false)
            return
          }

          setLoading(true)
          setError(null)

          try {
            // Обмен кода на токен через VK ID SDK
            console.log('Exchanging code for token...')
            const authData = await VKID.Auth.exchangeCode(code, deviceId)
            console.log('Auth data received:', { hasToken: !!authData?.access_token })

            if (!authData || !authData.access_token) {
              throw new Error('Не удалось получить токен от VK ID')
            }

            // Отправляем токен на бэкенд для создания/получения пользователя и JWT
            console.log('Sending token to backend...')
            const response = await axios.post(`${API_URL}/api/auth/vkid`, {
              access_token: authData.access_token,
            })

            if (response.data.token) {
              login(response.data.token)
              navigate('/analyze')
            } else {
              throw new Error('Не получен токен от сервера')
            }
          } catch (err) {
            console.error('VK ID authentication error:', err)
            const errorMessage = err.response?.data?.error || err.message || 'Ошибка авторизации'
            setError(errorMessage)
            setLoading(false)
          }
        })

      setInitialized(true)
    } catch (err) {
      console.error('Failed to initialize VK ID:', err)
      setError(`Не удалось инициализировать VK ID: ${err.message}`)
    }
  }, [initialized, API_URL, login, navigate])

  // Добавляем стили для кнопки VK ID после рендера
  useEffect(() => {
    if (containerRef.current && initialized) {
      // Применяем стили к кнопке VK ID после рендера
      const styleButton = () => {
        const buttons = containerRef.current?.querySelectorAll('button, a, [role="button"]')
        if (buttons && buttons.length > 0) {
          buttons.forEach((btn) => {
            btn.style.backgroundColor = '#0077FF'
            btn.style.background = '#0077FF'
            btn.style.borderColor = '#0077FF'
            btn.style.color = '#FFFFFF'
            btn.style.borderRadius = '6px'
          })
        }
      }

      // Применяем стили сразу и после небольшой задержки
      styleButton()
      const timeout = setTimeout(styleButton, 100)
      const interval = setInterval(styleButton, 500)

      return () => {
        clearTimeout(timeout)
        clearInterval(interval)
      }
    }
  }, [initialized])

  useEffect(() => {
    let checkInterval = null

    const initialize = () => {
      if (!containerRef.current || typeof window === 'undefined' || !window.VKIDSDK || initialized) {
        return
      }
      initializeVKID()
    }

    // Если SDK уже загружен, инициализируем сразу
    if (window?.VKIDSDK && containerRef.current) {
      initialize()
    } else {
      // Иначе ждем загрузки SDK
      checkInterval = setInterval(() => {
        if (window?.VKIDSDK && containerRef.current) {
          clearInterval(checkInterval)
          initialize()
        }
      }, 100)

      // Таймаут через 10 секунд - если SDK не загрузился, показываем ошибку
      setTimeout(() => {
        if (checkInterval) {
          clearInterval(checkInterval)
          if (!window?.VKIDSDK) {
            setError('Не удалось загрузить VK ID SDK. Проверьте подключение к интернету и консоль браузера для деталей.')
            console.error('VK ID SDK не загрузился за 10 секунд. Проверьте:')
            console.error('1. Подключение к интернету')
            console.error('2. Блокировку скриптов браузером/расширениями')
            console.error('3. Консоль браузера на наличие ошибок CORS или загрузки')
          }
        }
      }, 10000)
    }

    return () => {
      if (checkInterval) {
        clearInterval(checkInterval)
      }
    }
  }, [initializeVKID, initialized])

  // Если SDK не загрузился и нет ошибки, показываем сообщение
  if (typeof window !== 'undefined' && !window.VKIDSDK && !error && !initialized) {
    return (
      <div className={`flex flex-col items-center ${className}`}>
        <p className="text-sm text-gray-500">Загрузка VK ID...</p>
      </div>
    )
  }

  return (
    <div className={`flex flex-col items-center ${className}`}>
      <div className="vkid-button-container">
        <div
          ref={containerRef}
          className="flex justify-center"
          style={{ minHeight: '48px', minWidth: '200px' }}
        />
      </div>
      {loading && (
        <p className="mt-3 text-sm text-gray-500">Авторизация...</p>
      )}
      {error && (
        <div className="mt-3 text-sm text-red-500 text-center max-w-md">
          {error}
        </div>
      )}
    </div>
  )
}
