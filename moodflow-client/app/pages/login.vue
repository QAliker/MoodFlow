<script setup lang="ts">
definePageMeta({ middleware: [], layout: false })

const { login } = useAuth()
const toast = useToast()

const form = reactive({ email: '', password: '' })
const errors = ref<Record<string, string>>({})
const loading = ref(false)

async function onSubmit() {
  loading.value = true
  errors.value = {}
  try {
    await login(form.email, form.password)
  } catch (err: unknown) {
    const e = err as { data?: { errors?: Record<string, string[]>; message?: string } }
    if (e.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(e.data.errors).map(([k, v]) => [k, v[0]]),
      )
    } else {
      toast.add({ title: e.data?.message ?? 'An error occurred', color: 'error' })
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 p-4">
    <UCard class="w-full max-w-sm">
      <template #header>
        <h1 class="text-xl font-semibold text-center">Sign in to MoodFlow</h1>
      </template>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <UFormField label="Email" :error="errors.email">
          <UInput
            v-model="form.email"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            class="w-full"
          />
        </UFormField>

        <UFormField label="Password" :error="errors.password">
          <UInput
            v-model="form.password"
            type="password"
            placeholder="••••••••"
            autocomplete="current-password"
            class="w-full"
          />
        </UFormField>

        <UButton type="submit" label="Sign in" :loading="loading" block />
      </form>

      <template #footer>
        <p class="text-center text-sm text-gray-500">
          No account yet?
          <NuxtLink to="/register" class="text-primary-500 hover:underline">
            Register
          </NuxtLink>
        </p>
      </template>
    </UCard>
  </div>
</template>
