<script setup>
import { onMounted, reactive } from 'vue'
import { api, responseData } from '../api'

const providers = reactive({ google: false, facebook: false })

onMounted(async () => {
  try {
    Object.assign(providers, responseData(await api.get('/auth/oauth/providers')))
  } catch {
    // Providers stay disabled when capabilities cannot be loaded.
  }
})

function continueWith(provider) {
  if (!providers[provider]) return
  window.location.assign(`${api.defaults.baseURL}/auth/oauth/${provider}/redirect`)
}
</script>

<template>
  <div>
    <div class="social-divider">Hoặc tiếp tục với</div>
    <div class="social-grid">
      <button v-for="provider in ['google', 'facebook']" :key="provider" class="social-button" type="button" :disabled="!providers[provider]" :aria-disabled="!providers[provider]" @click="continueWith(provider)">
        {{ provider === 'google' ? 'Google' : 'Facebook' }} <small v-if="!providers[provider]">Chưa cấu hình</small>
      </button>
    </div>
  </div>
</template>
