// Функция для получения API URL
// Если VITE_API_URL задан (встроен в сборку), используем его
// Иначе определяем из текущего домена
export function getApiUrl() {
  // Проверяем встроенную переменную окружения
  if (import.meta.env.VITE_API_URL && import.meta.env.VITE_API_URL !== 'http://localhost:8080') {
    return import.meta.env.VITE_API_URL
  }
  
  // Определяем из текущего URL
  if (typeof window !== 'undefined') {
    const origin = window.location.origin
    return origin
  }
  
  // Fallback для SSR или других случаев
  return 'http://localhost:8080'
}

export const API_URL = getApiUrl()

