export interface Category {
    id: number;
    name: string;
}

export interface Ingredient {
    id: number;
    name: string;
    unit: string;
}

export interface RecipeIngredient {
    ingredient_id: number;
    quantity: string;
    unit: string;
}

export interface Product {
    id: number;
    name: string;
    category: string;
    price: string;
    image: string | null;
    image_url: string | null;
    is_available: boolean;
    tracking_type: 'recipe' | 'finished_stock';
    stock_quantity: number | null;
    recipe: {
        ingredient_id: number;
        quantity: string;
        unit: string;
        ingredient: Ingredient;
    }[];
}