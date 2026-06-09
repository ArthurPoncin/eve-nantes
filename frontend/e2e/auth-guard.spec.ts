import { expect, test } from '@playwright/test'

test('redirige vers la connexion quand on visite une page protégée sans être connecté', async ({
  page,
}) => {
  await page.goto('/profil')

  // La garde requireAuth renvoie vers /login en conservant la cible dans ?redirect=.
  await expect(page).toHaveURL(/\/login\?redirect=\/profil/)
  await expect(page.getByRole('heading', { name: 'Bon retour' })).toBeVisible()
})

test('redirige aussi la page favoris protégée vers la connexion', async ({ page }) => {
  await page.goto('/favoris')

  await expect(page).toHaveURL(/\/login\?redirect=\/favoris/)
})
