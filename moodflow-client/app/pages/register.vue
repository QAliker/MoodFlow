<script setup lang="ts">
definePageMeta({ middleware: [], layout: false })

const { register } = useAuth()
const toast = useToast()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const loading = ref(false)

async function onSubmit() {
  loading.value = true
  errors.value = {}
  try {
    await register(form.name, form.email, form.password, form.password_confirmation)
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
        <h1 class="text-xl font-semibold text-center">Create your account</h1>
      </template>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <UFormField label="Name" :error="errors.name">
          <UInput
            v-model="form.name"
            placeholder="Your name"
            autocomplete="name"
            class="w-full"
          />
        </UFormField>

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
            placeholder="Min. 8 characters"
            autocomplete="new-password"
            class="w-full"
          />
        </UFormField>

        <UFormField label="Confirm password" :error="errors.password_confirmation">
          <UInput
            v-model="form.password_confirmation"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            class="w-full"
          />
        </UFormField>

        <UButton type="submit" label="Create account" :loading="loading" block />
      </form>

      <template #footer>
        <p class="text-center text-sm text-gray-500">
          Already have an account?
          <NuxtLink to="/login" class="text-primary-500 hover:underline">
            Sign in
          </NuxtLink>
        </p>
      </template>
    </UCard>
  </div>
</template>
